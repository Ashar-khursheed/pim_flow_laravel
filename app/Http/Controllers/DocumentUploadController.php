<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use ZipArchive;
use App\Models\Product;
use Illuminate\Support\Str;
use App\Helpers\PdfHelper;

/**
 * DocumentUploadController
 * 
 * Handles document upload operations for products including:
 * - Bulk document upload from ZIP files
 * - Single document upload
 * - Document deletion
 * - PDF compression with quality preservation
 * - S3 storage integration
 */
class DocumentUploadController extends Controller
{
    /**
     * Upload product documents from zip file to S3 and update database
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */

    /**
     * Upload product documents from a zip file.
     *
     * @OA\Post(
     *     path="/api/product/upload-documents",
     *     summary="Upload product documents from zip file",
     *     description="Upload a ZIP file containing product documents organized by SKU folders, extract and process them to S3, and update product records in the database.",
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
     *                     description="ZIP file containing product documents organized in folders by SKU (max 500MB)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Documents processed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product documents processed successfully"),
     *             @OA\Property(
     *                 property="processed_skus",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="sku", type="string", example="ABC123"),
     *                     @OA\Property(property="status", type="string", example="success", description="success, no_documents_found, or product_not_found"),
     *                     @OA\Property(property="document_count", type="integer", example=5, description="Number of documents processed for this SKU"),
     *                     @OA\Property(property="compressed_count", type="integer", example=2, description="Number of PDFs that were compressed")
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
    public function uploadProductDocuments(Request $request)
    {
        // Validate the uploaded file - increased limit to handle larger documents
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

            // Process the extracted directory with improved handling
            $processedSkus = $this->processExtractedDirectory($tempPath);

            // Clean up the temporary directory
            File::deleteDirectory($tempPath);

            return response()->json([
                'success' => true,
                'message' => 'Product documents processed successfully',
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
     * Process the extracted directory structure with improved nested folder handling
     * 
     * Handles both flat structure (SKU/documents) and nested structure (SKU/SKU/documents)
     *
     * @param string $extractPath Path to the extracted ZIP contents
     * @return array Array of processed SKU results
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
                // Look for documents in the SKU directory and its subdirectories
                $documentsFound = $this->findDocumentsInDirectory($skuDir, $sku);
                
                if (!empty($documentsFound['documents'])) {
                    // Update the product record with new document data
                    $product->documents = json_encode($documentsFound['documents']);
                    $product->save();
                    
                    $processedSkus[] = [
                        'sku' => $sku,
                        'status' => 'success',
                        'document_count' => count($documentsFound['documents']),
                        'compressed_count' => $documentsFound['compressed_count']
                    ];
                } else {
                    $processedSkus[] = [
                        'sku' => $sku,
                        'status' => 'no_documents_found',
                        'document_count' => 0,
                        'compressed_count' => 0
                    ];
                }
            } else {
                $processedSkus[] = [
                    'sku' => $sku,
                    'status' => 'product_not_found',
                    'document_count' => 0,
                    'compressed_count' => 0
                ];
            }
        }
        
        return $processedSkus;
    }

    /**
     * Recursively find documents in directory and subdirectories
     * 
     * Supports nested folder structures and finds all document files recursively
     *
     * @param string $directory Path to search for documents
     * @param string $sku SKU identifier for logging
     * @return array Array containing documents and compression count
     */
    private function findDocumentsInDirectory($directory, $sku)
    {
        $allDocuments = [];
        $compressedCount = 0;
        
        // Get all files recursively from directory and subdirectories
        $items = File::allFiles($directory);
        
        // Filter for document files only
        $documentFiles = array_filter($items, function($file) {
            $extension = strtolower($file->getExtension());
            return in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt']);
        });

        if (empty($documentFiles)) {
            return ['documents' => [], 'compressed_count' => 0];
        }

        // Process each document file
        foreach ($documentFiles as $documentFile) {
            $result = $this->processDocumentFile($documentFile, $sku);
            
            if ($result['success']) {
                $allDocuments[] = $result['document'];
                if ($result['compressed']) {
                    $compressedCount++;
                }
            }
        }
        
        return [
            'documents' => $allDocuments,
            'compressed_count' => $compressedCount
        ];
    }

    /**
     * Process individual document file with improved size handling and quality preservation
     * 
     * Features:
     * - Smart PDF compression with quality preservation
     * - Multiple compression levels testing
     * - Size optimization targeting 1-2MB
     * - Quality-first approach
     *
     * @param \SplFileInfo $documentFile File to process
     * @param string $sku SKU identifier for logging
     * @return array Processing result with success status and document data
     */
    private function processDocumentFile($documentFile, $sku)
    {
        $originalFileName = $documentFile->getFilename();
        $extension = strtolower($documentFile->getExtension());
        $fileToUpload = $documentFile->getPathname();
        $compressed = false;
        
        // Log file info for debugging
        \Log::info("Processing document", [
            'sku' => $sku,
            'file' => $originalFileName,
            'size_mb' => round($documentFile->getSize() / 1048576, 2),
            'extension' => $extension,
            'path' => $documentFile->getPathname()
        ]);

        // Create temporary directory for compression
        $compressedDir = storage_path('app/temp/compressed/' . Str::random(10));
        File::makeDirectory($compressedDir, 0755, true);

        try {
            // Check initial file size - increased limit to 50MB before compression
            $initialSize = $documentFile->getSize();
            $initialSizeMB = round($initialSize / 1048576, 2);
            
            // Skip files larger than 50MB initially
            if ($initialSize > 52428800) { // 50MB
                \Log::warning("File too large to process", [
                    'sku' => $sku,
                    'file' => $originalFileName,
                    'size_mb' => $initialSizeMB
                ]);
                return ['success' => false, 'reason' => 'file_too_large'];
            }

            // Attempt compression for PDFs - target 1-2MB with better quality
            if ($extension === 'pdf') {
                $compressedFileName = pathinfo($originalFileName, PATHINFO_FILENAME) . '_compressed.pdf';
                
                // Target size range: 1-2MB
                $targetMinSize = 1048576;  // 1MB
                $targetMaxSize = 2097152;  // 2MB
                
                // Try different compression levels with quality preservation
                $compressionLevels = [
                    'printer',  // Best quality, larger size
                    'ebook',    // Good balance
                    'screen'    // Smallest size, lower quality
                ];
                
                $bestCompression = null;
                $bestSize = $initialSize;
                $foundTargetRange = false;
                
                foreach ($compressionLevels as $level) {
                    $tempCompressedPath = $compressedDir . '/' . $level . '_' . $compressedFileName;
                    $compressionResult = PdfHelper::compressPdf($fileToUpload, $tempCompressedPath, $level);
                    
                    if (is_array($compressionResult) && 
                        $compressionResult['exit_code'] === 0 && 
                        file_exists($tempCompressedPath)) {
                        
                        $compressedSize = filesize($tempCompressedPath);
                        
                        // Check if we hit the target range (1-2MB)
                        if ($compressedSize >= $targetMinSize && $compressedSize <= $targetMaxSize) {
                            $bestSize = $compressedSize;
                            $bestCompression = $tempCompressedPath;
                            $foundTargetRange = true;
                            
                            \Log::info("PDF compression hit target range with good quality", [
                                'sku' => $sku,
                                'file' => $originalFileName,
                                'level' => $level,
                                'size_mb' => round($compressedSize / 1048576, 2)
                            ]);
                            break; // Found perfect size with best quality, stop here
                        }
                        
                        // If not in target range, keep track but continue to try better quality options
                        if ($compressedSize < $bestSize && $compressedSize > 0) {
                            $bestSize = $compressedSize;
                            $bestCompression = $tempCompressedPath;
                        }
                    }
                }
                
                // Only use compression if file is significantly large (>3MB) or we found good compression
                $shouldCompress = ($initialSize > 3145728) || $foundTargetRange; // 3MB threshold
                
                if ($bestCompression && $bestSize < $initialSize && $shouldCompress) {
                    $fileToUpload = $bestCompression;
                    $compressed = true;
                    
                    $finalSizeMB = round($bestSize / 1048576, 2);
                    $compressionRatio = round((1 - $bestSize / $initialSize) * 100, 2);
                    
                    \Log::info("PDF compressed successfully", [
                        'sku' => $sku,
                        'original_file' => $originalFileName,
                        'original_size_mb' => $initialSizeMB,
                        'compressed_size_mb' => $finalSizeMB,
                        'compression_ratio' => $compressionRatio . '%',
                        'in_target_range' => $foundTargetRange ? 'yes (1-2MB)' : 'no',
                        'quality_preserved' => 'yes'
                    ]);
                } else {
                    \Log::info("PDF compression skipped - file already optimal size", [
                        'sku' => $sku,
                        'file' => $originalFileName,
                        'original_size_mb' => $initialSizeMB,
                        'reason' => !$shouldCompress ? 'file_under_3mb' : 'compression_ineffective'
                    ]);
                }
            }

            // Final size check - allow up to 5MB after compression
            $finalFileSize = filesize($fileToUpload);
            $finalFileSizeMB = round($finalFileSize / 1048576, 2);
            
            if ($finalFileSize > 5242880) { // 5MB
                \Log::warning("File still too large after compression", [
                    'sku' => $sku,
                    'file' => $originalFileName,
                    'final_size_mb' => $finalFileSizeMB,
                    'note' => 'Consider manual compression or file optimization'
                ]);
                return ['success' => false, 'reason' => 'file_too_large_after_compression'];
            }

            // Generate a unique filename to prevent overwriting
            $uniqueFileName = Str::random(40) . '.' . pathinfo($originalFileName, PATHINFO_EXTENSION);
            $s3FilePath = 'production/documents/' . $uniqueFileName;

            // Upload file to S3 using stream for memory efficiency
            $fileStream = fopen($fileToUpload, 'r');
            if ($fileStream === false) {
                \Log::error("Failed to open file for upload", [
                    'sku' => $sku,
                    'file' => $fileToUpload
                ]);
                return ['success' => false, 'reason' => 'file_read_error'];
            }
            
            Storage::disk('s3')->put($s3FilePath, $fileStream);
            fclose($fileStream);

            // Get the full URL from S3 storage
            $documentUrl = Storage::disk('s3')->url($s3FilePath);

            return [
                'success' => true,
                'document' => [
                    'title' => $originalFileName,
                    'path' => $documentUrl,
                    'compressed' => $compressed,
                    'size_mb' => $finalFileSizeMB
                ],
                'compressed' => $compressed
            ];

        } finally {
            // Always clean up compressed files directory
            if (File::exists($compressedDir)) {
                File::deleteDirectory($compressedDir);
            }
        }
    }

    /**
     * Upload individual document file to S3 with PDF compression
     *
     * @OA\Post(
     *     path="/api/product/upload-single-document",
     *     summary="Upload single product document",
     *     description="Upload a single document file for a specific product SKU with automatic PDF compression",
     *     tags={"Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="document_file",
     *                     type="string",
     *                     format="binary",
     *                     description="Document file (PDF, DOC, DOCX, XLS, XLSX, TXT) - max 10MB"
     *                 ),
     *                 @OA\Property(
     *                     property="sku",
     *                     type="string",
     *                     description="Product SKU"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Document uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Document uploaded successfully"),
     *             @OA\Property(
     *                 property="document",
     *                 type="object",
     *                 @OA\Property(property="title", type="string", example="manual.pdf"),
     *                 @OA\Property(property="path", type="string", example="https://s3.amazonaws.com/bucket/path/file.pdf"),
     *                 @OA\Property(property="compressed", type="boolean", example=true),
     *                 @OA\Property(property="size_mb", type="number", example=1.5)
     *             ),
     *             @OA\Property(property="compressed", type="boolean", example=true)
     *         )
     *     )
     * )
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadSingleDocument(Request $request)
    {
        // Validate the uploaded file and SKU
        $request->validate([
            'document_file' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,txt|max:10240', // 10MB max size
            'sku' => 'required|string|exists:products,sku'
        ]);

        try {
            // Get the SKU
            $sku = $request->input('sku');
            
            // Find product with this SKU
            $product = Product::where('sku', $sku)->first();
            
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'error' => 'Product not found'
                ], 404);
            }
            
            // Get the uploaded file
            $documentFile = $request->file('document_file');
            $originalFileName = $documentFile->getClientOriginalName();
            $extension = strtolower($documentFile->getClientOriginalExtension());
            $fileToUpload = $documentFile->getRealPath();
            $compressed = false;

            // Create temporary directory for compression
            $tempDir = storage_path('app/temp/single/' . Str::random(10));
            File::makeDirectory($tempDir, 0755, true);

            try {
                // Compress PDF files using the same logic as bulk upload
                if ($extension === 'pdf') {
                    $initialSize = $documentFile->getSize();
                    $targetMinSize = 1048576;  // 1MB
                    $targetMaxSize = 2097152;  // 2MB
                    
                    $compressionLevels = ['printer', 'ebook', 'screen'];
                    $bestCompression = null;
                    $bestSize = $initialSize;
                    $foundTargetRange = false;
                    
                    foreach ($compressionLevels as $level) {
                        $compressedFileName = pathinfo($originalFileName, PATHINFO_FILENAME) . '_compressed_' . $level . '.pdf';
                        $compressedFilePath = $tempDir . '/' . $compressedFileName;
                        
                        $compressionResult = PdfHelper::compressPdf($fileToUpload, $compressedFilePath, $level);
                        
                        if (is_array($compressionResult) && 
                            $compressionResult['exit_code'] === 0 && 
                            file_exists($compressedFilePath)) {
                            
                            $compressedSize = filesize($compressedFilePath);
                            
                            if ($compressedSize >= $targetMinSize && $compressedSize <= $targetMaxSize) {
                                $bestSize = $compressedSize;
                                $bestCompression = $compressedFilePath;
                                $foundTargetRange = true;
                                break;
                            }
                            
                            if ($compressedSize < $bestSize && $compressedSize > 0) {
                                $bestSize = $compressedSize;
                                $bestCompression = $compressedFilePath;
                            }
                        }
                    }
                    
                    $shouldCompress = ($initialSize > 3145728) || $foundTargetRange;
                    
                    if ($bestCompression && $bestSize < $initialSize && $shouldCompress) {
                        $fileToUpload = $bestCompression;
                        $compressed = true;
                    }
                }

                // Generate a unique filename
                $uniqueFileName = Str::random(40) . '.' . $extension;
                $s3FilePath = 'production/documents/' . $uniqueFileName;
                
                // Upload the file to S3
                Storage::disk('s3')->put($s3FilePath, file_get_contents($fileToUpload));
                
                // Get the full URL from S3 storage
                $documentUrl = Storage::disk('s3')->url($s3FilePath);
                
                // Create document data
                $newDocument = [
                    'title' => $originalFileName,
                    'path' => $documentUrl,
                    'compressed' => $compressed,
                    'size_mb' => round(filesize($fileToUpload) / 1048576, 2)
                ];
                
                // Get current documents or initialize empty array
                $currentDocuments = $product->documents ? json_decode($product->documents, true) : [];
                
                // Add new document
                $currentDocuments[] = $newDocument;
                
                // Update product with new documents array
                $product->documents = json_encode($currentDocuments);
                $product->save();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Document uploaded successfully',
                    'document' => $newDocument,
                    'compressed' => $compressed
                ]);

            } finally {
                // Clean up temporary directory
                if (File::exists($tempDir)) {
                    File::deleteDirectory($tempDir);
                }
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a document from a product
     *
     * @OA\Delete(
     *     path="/api/product/delete-document",
     *     summary="Delete product document",
     *     description="Delete a specific document from a product and remove it from S3 storage",
     *     tags={"Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="sku", type="string", description="Product SKU"),
     *             @OA\Property(property="document_path", type="string", description="Full S3 URL of the document to delete")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Document deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Document deleted successfully")
     *         )
     *     )
     * )
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteDocument(Request $request)
    {
        // Validate the request
        $request->validate([
            'sku' => 'required|string|exists:products,sku',
            'document_path' => 'required|string'
        ]);

        try {
            // Get the SKU and document path
            $sku = $request->input('sku');
            $documentPath = $request->input('document_path');
            
            // Find product with this SKU
            $product = Product::where('sku', $sku)->first();
            
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'error' => 'Product not found'
                ], 404);
            }
            
            // Get current documents
            $currentDocuments = $product->documents ? json_decode($product->documents, true) : [];
            
            // Find the index of the document to delete
            $documentIndex = null;
            foreach ($currentDocuments as $index => $document) {
                if ($document['path'] === $documentPath) {
                    $documentIndex = $index;
                    break;
                }
            }
            
            if ($documentIndex === null) {
                return response()->json([
                    'success' => false,
                    'error' => 'Document not found'
                ], 404);
            }
            
            // Extract S3 path from URL
            $s3Path = parse_url($documentPath, PHP_URL_PATH);
            $s3Path = ltrim($s3Path, '/');
            
            // Try to delete the file from S3
            if (Storage::disk('s3')->exists($s3Path)) {
                Storage::disk('s3')->delete($s3Path);
            }
            
            // Remove document from array
            array_splice($currentDocuments, $documentIndex, 1);
            
            // Update product with new documents array
            $product->documents = json_encode($currentDocuments);
            $product->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test Ghostscript availability and system configuration
     * 
     * @OA\Get(
     *     path="/api/test-ghostscript",
     *     summary="Test Ghostscript configuration",
     *     description="Check if Ghostscript is properly installed and configured for PDF compression",
     *     tags={"System"},
     *     @OA\Response(
     *         response=200,
     *         description="System configuration status",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="ghostscript_status",
     *                 type="object",
     *                 @OA\Property(property="available", type="boolean"),
     *                 @OA\Property(property="path", type="string"),
     *                 @OA\Property(property="version", type="string")
     *             ),
     *             @OA\Property(
     *                 property="php_info",
     *                 type="object",
     *                 @OA\Property(property="max_execution_time", type="string"),
     *                 @OA\Property(property="memory_limit", type="string"),
     *                 @OA\Property(property="upload_max_filesize", type="string"),
     *                 @OA\Property(property="post_max_size", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function testGhostscript()
    {
        $result = PdfHelper::checkGhostscriptAvailability();
        
        return response()->json([
            'ghostscript_status' => $result,
            'php_info' => [
                'max_execution_time' => ini_get('max_execution_time'),
                'memory_limit' => ini_get('memory_limit'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'current_time' => date('Y-m-d H:i:s'),
                'server_os' => PHP_OS
            ],
            's3_config' => [
                'disk_configured' => config('filesystems.disks.s3') ? true : false,
                'bucket' => config('filesystems.disks.s3.bucket', 'not_configured')
            ]
        ]);
    }
}