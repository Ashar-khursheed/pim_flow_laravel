<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use ZipArchive;
use App\Models\Product;
use Illuminate\Support\Str;

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
     *                     description="ZIP file containing product documents organized in folders by SKU"
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
     *                     @OA\Property(property="document_count", type="integer", example=5, description="Number of documents processed for this SKU")
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
                // Process documents for this product
                $documentData = $this->uploadProductDocumentsToS3($skuDir, $sku);
                
                if (!empty($documentData)) {
                    // Update the product record with new document data
                    $product->documents = json_encode($documentData);
                    $product->save();
                    
                    $processedSkus[] = [
                        'sku' => $sku,
                        'status' => 'success',
                        'document_count' => count($documentData)
                    ];
                } else {
                    $processedSkus[] = [
                        'sku' => $sku,
                        'status' => 'no_documents_found'
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
     * Upload documents to S3 and return array of document data
     *
     * @param string $documentsDir
     * @param string $sku
     * @return array
     */
    // private function uploadProductDocumentsToS3($documentsDir, $sku)
    // {
    //     $s3Path = 'production/documents/';
    //     $documentData = [];
        
    //     // Get all document files in the SKU directory
    //     $documentFiles = File::files($documentsDir);
        
    //     // Filter for document files only
    //     $documentFiles = array_filter($documentFiles, function($file) {
    //         $extension = strtolower($file->getExtension());
    //         return in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt']);
    //     });
        
    //     if (empty($documentFiles)) {
    //         return $documentData;
    //     }
        
    //     // Upload each document directly to S3
    //     foreach ($documentFiles as $documentFile) {
    //         // Generate a unique filename to prevent overwriting
    //         $uniqueFileName = Str::random(40) . '.' . $documentFile->getExtension();
    //         $s3FilePath = $s3Path . $uniqueFileName;
            
    //         // Original filename to use as title
    //         $originalFileName = $documentFile->getFilename();
            
    //         // Open file and directly upload to S3
    //         $fileStream = fopen($documentFile->getPathname(), 'r');
    //         Storage::disk('s3')->put($s3FilePath, $fileStream);
    //         fclose($fileStream);
            
    //         // Get the full URL from S3 storage
    //         $documentUrl = Storage::disk('s3')->url($s3FilePath);
            
    //         // Add the document data to the array
    //         $documentData[] = [
    //             'title' => $originalFileName,
    //             'path' => $documentUrl
    //         ];
    //     }
        
    //     return $documentData;
    // }
    private function uploadProductDocumentsToS3($documentsDir, $sku)
    {
        $s3Path = 'production/documents/';
        $documentData = [];

        // Get all document files in the SKU directory
        $documentFiles = File::files($documentsDir);

        // Filter for document files only
        $documentFiles = array_filter($documentFiles, function($file) {
            $extension = strtolower($file->getExtension());
            return in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt']);
        });

        if (empty($documentFiles)) {
            return $documentData;
        }

        // Upload each document directly to S3
        foreach ($documentFiles as $documentFile) {
            // Check file size (2 MB = 2,097,152 bytes)
            if ($documentFile->getSize() > 2097152) {
                // Skip files larger than 2MB
                continue;
            }

            // Generate a unique filename to prevent overwriting
            $uniqueFileName = Str::random(40) . '.' . $documentFile->getExtension();
            $s3FilePath = $s3Path . $uniqueFileName;

            // Original filename to use as title
            $originalFileName = $documentFile->getFilename();

            // Open file and directly upload to S3
            $fileStream = fopen($documentFile->getPathname(), 'r');
            Storage::disk('s3')->put($s3FilePath, $fileStream);
            fclose($fileStream);

            // Get the full URL from S3 storage
            $documentUrl = Storage::disk('s3')->url($s3FilePath);

            // Add the document data to the array
            $documentData[] = [
                'title' => $originalFileName,
                'path' => $documentUrl
            ];
        }

        return $documentData;
    }


    /**
     * Upload individual document file to S3
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
            
            // Generate a unique filename
            $uniqueFileName = Str::random(40) . '.' . $documentFile->getClientOriginalExtension();
            $s3FilePath = 'production/documents/' . $uniqueFileName;
            
            // Upload the file to S3
            Storage::disk('s3')->put($s3FilePath, file_get_contents($documentFile->getRealPath()));
            
            // Get the full URL from S3 storage
            $documentUrl = Storage::disk('s3')->url($s3FilePath);
            
            // Create document data
            $newDocument = [
                'title' => $originalFileName,
                'path' => $documentUrl
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
                'document' => $newDocument
            ]);
            
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
}