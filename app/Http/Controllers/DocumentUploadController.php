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

class DocumentUploadController extends Controller
{
    // ... (keep your existing OpenAPI documentation)
    
    public function uploadProductDocuments(Request $request)
    {
        // Increase file size limit to handle larger documents
        $request->validate([
            'zip_file' => 'required|file|mimes:zip|max:512000', // 500MB max size
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

            // Process the extracted directory with improved handling
            $processedSkus = $this->processExtractedDirectory($tempPath);

            File::deleteDirectory($tempPath);

            return response()->json([
                'success' => true,
                'message' => 'Product documents processed successfully',
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

    /**
     * Process the extracted directory structure with improved nested folder handling
     */
    private function processExtractedDirectory($extractPath)
    {
        $processedSkus = [];
        
        // Get all directories in the extracted path
        $skuDirectories = File::directories($extractPath);
        
        foreach ($skuDirectories as $skuDir) {
            $sku = basename($skuDir);
            
            // Find product with this SKU
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
     */
    private function findDocumentsInDirectory($directory, $sku)
    {
        $allDocuments = [];
        $compressedCount = 0;
        
        // Get all files and subdirectories
        $items = File::allFiles($directory);
        
        // Filter for document files
        $documentFiles = array_filter($items, function($file) {
            $extension = strtolower($file->getExtension());
            return in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt']);
        });

        if (empty($documentFiles)) {
            return ['documents' => [], 'compressed_count' => 0];
        }

        // Process each document
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
     * Process individual document file with improved size handling
     */
    private function processDocumentFile($documentFile, $sku)
    {
        $originalFileName = $documentFile->getFilename();
        $extension = strtolower($documentFile->getExtension());
        $fileToUpload = $documentFile->getPathname();
        $compressed = false;
        
        // Log file info for debugging
        \Log::info("Processing document", [
            'file' => $originalFileName,
            'size_mb' => round($documentFile->getSize() / 1048576, 2),
            'extension' => $extension
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
                    'file' => $originalFileName,
                    'size_mb' => $initialSizeMB
                ]);
                return ['success' => false, 'reason' => 'file_too_large'];
            }

            // Attempt compression for PDFs
            if ($extension === 'pdf') {
                $compressedFileName = pathinfo($originalFileName, PATHINFO_FILENAME) . '_compressed.pdf';
                $compressedFilePath = $compressedDir . '/' . $compressedFileName;
                
                // Try different compression levels
                $compressionLevels = ['screen', 'ebook', 'printer'];
                $bestCompression = null;
                $bestSize = $initialSize;
                
                foreach ($compressionLevels as $level) {
                    $tempCompressedPath = $compressedDir . '/' . $level . '_' . $compressedFileName;
                    $compressionResult = PdfHelper::compressPdf($fileToUpload, $tempCompressedPath, $level);
                    
                    if (is_array($compressionResult) && 
                        $compressionResult['exit_code'] === 0 && 
                        file_exists($tempCompressedPath)) {
                        
                        $compressedSize = filesize($tempCompressedPath);
                        
                        if ($compressedSize < $bestSize && $compressedSize > 0) {
                            $bestSize = $compressedSize;
                            $bestCompression = $tempCompressedPath;
                            
                            // If we get under 15MB, use this compression
                            if ($compressedSize <= 15728640) { // 15MB
                                break;
                            }
                        }
                    }
                }
                
                if ($bestCompression && $bestSize < $initialSize) {
                    $fileToUpload = $bestCompression;
                    $compressed = true;
                    
                    \Log::info("PDF compressed successfully", [
                        'original_file' => $originalFileName,
                        'original_size_mb' => $initialSizeMB,
                        'compressed_size_mb' => round($bestSize / 1048576, 2),
                        'compression_ratio' => round((1 - $bestSize / $initialSize) * 100, 2) . '%'
                    ]);
                } else {
                    \Log::warning("PDF compression didn't help or failed", [
                        'file' => $originalFileName,
                        'original_size_mb' => $initialSizeMB
                    ]);
                }
            }

            // Final size check - allow up to 25MB after compression
            $finalFileSize = filesize($fileToUpload);
            $finalFileSizeMB = round($finalFileSize / 1048576, 2);
            
            if ($finalFileSize > 26214400) { // 25MB
                \Log::warning("File still too large after compression", [
                    'file' => $originalFileName,
                    'final_size_mb' => $finalFileSizeMB
                ]);
                return ['success' => false, 'reason' => 'file_too_large_after_compression'];
            }

            // Generate unique filename and upload to S3
            $uniqueFileName = Str::random(40) . '.' . pathinfo($originalFileName, PATHINFO_EXTENSION);
            $s3FilePath = 'production/documents/' . $uniqueFileName;

            // Upload to S3
            $fileStream = fopen($fileToUpload, 'r');
            if ($fileStream === false) {
                \Log::error("Failed to open file for upload", ['file' => $fileToUpload]);
                return ['success' => false, 'reason' => 'file_read_error'];
            }
            
            Storage::disk('s3')->put($s3FilePath, $fileStream);
            fclose($fileStream);

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
            // Clean up compressed files directory
            if (File::exists($compressedDir)) {
                File::deleteDirectory($compressedDir);
            }
        }
    }

    // ... (keep your existing uploadSingleDocument and deleteDocument methods)
}