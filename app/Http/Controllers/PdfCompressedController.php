<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Exception;
class PdfCompressedController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/upload-pdf-compressed",
     *     summary="Upload product documents from a PDF file",
     *     description="Upload a PDF file containing product documents, process them, upload to S3, and update product records in the database.",
     *     tags={"PDF File Upload"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"pdf_file"},
     *                 @OA\Property(
     *                     property="pdf_file",
     *                     type="string",
     *                     format="binary",
     *                     description="PDF file containing product documents"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Documents processed successfully",     *     
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input",
     *          
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
     *                     property="pdf_file",
     *                     type="array",
     *                     @OA\Items(type="string", example="The pdf file field is required.")
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
    public function uploadPdfCompressed(Request $request)
    {
        // Validate the uploaded file
        $request->validate([
            'pdf_file' => 'required|file|mimes:pdf|max:102400', // 100MB max size
        ]);

        $pdf = $request->file('pdf_file');
        $path = $pdf->store('pdfs', 'public');
        $filePath = storage_path("app/public/{$path}");
        if (!file_exists($filePath)) {
            mkdir($filePath, 0777, true);
        }
        $fileSize = filesize($filePath);
        try {
            // If file size > 2MB, compress using Ghostscript
            if ($fileSize > 2 * 1024 * 1024) {

                $compressedPath = realpath(storage_path("app/public/pdfs")) . DIRECTORY_SEPARATOR . "compressed_" . $pdf->getClientOriginalName();
                $filePath = realpath($filePath);

                 
                $outputFile = '"' . $compressedPath . '"';
                $inputFile = '"' . $filePath . '"';


                $gsPath = '"C:\\Program Files\\gs\\gs10.05.1\\bin\\gswin64c.exe"';
                // Ghostscript command
                $cmd = $gsPath." -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/ebook"
                    . "-dNOPAUSE -dQUIET -dBATCH -sOutputFile=" . escapeshellarg($outputFile)
                    . " " . escapeshellarg($inputFile);

                exec($cmd . " 2>&1", $output, $returnVar);
                //exec($cmd, $output, $returnVar);
                if ($returnVar === 0 && file_exists($compressedPath)) {
                    rename($compressedPath, $filePath);
                } else {
                    throw new \Exception("Ghostscript failed. Return: $returnVar, Output: " . implode("\n", $output));
                }
            }
        } catch (Exception $e) {
            echo "<pre>";
            print_r($e->getMessage());
        }

        return response()->json([
            'message' => 'PDF uploaded successfully ff!',
            'file_url' => asset("storage/{$path}")
        ]);

    }
    public function uploadPdfCompressed_old(Request $request)
    {
        // Validate the uploaded file
        $request->validate([
            'pdf_file' => 'required|file|mimes:pdf|max:102400', // 100MB max size
        ]);

        try {
            // Store the uploaded PDF
            $pdf = $request->file('pdf_file');
            $path = $pdf->store('pdfs', 'public');
            $filePath = storage_path("app/public/{$path}");

            // Check if file was stored successfully
            if (!file_exists($filePath)) {
                throw new \Exception("Failed to store the PDF file.");
            }

            $fileSize = filesize($filePath);

            // Attempt compression only if file size > 2MB
            if ($fileSize > 2 * 1024 * 1024) {
                // Ensure "pdfs" folder exists
                $outputDir = storage_path("app/public/pdfs");
                if (!file_exists($outputDir)) {
                    mkdir($outputDir, 0755, true);
                }

                // Generate compressed file path
                $safeFileName = preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', $pdf->getClientOriginalName());
                $compressedPath = $outputDir . "/compressed_" . $safeFileName;

                // Detect platform and set Ghostscript command
                $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
                $gsCommand = $isWindows ? 'gswin64c' : 'gs';

                // Check if Ghostscript is available
                $gsCheck = shell_exec($isWindows ? 'where gswin64c 2>&1' : 'which gs 2>&1');
                if (empty($gsCheck) || strpos($gsCheck, 'not found') !== false) {

                    // Skip compression and proceed with original file
                    return response()->json([
                        'message' => 'PDF uploaded successfully (compression skipped: Ghostscript not installed)!',
                        'file_url' => asset("storage/{$path}"),
                    ]);
                }

                // Ghostscript command for compression
                $cmd = sprintf(
                    '%s -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/ebook -dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s',
                    $gsCommand,
                    escapeshellarg($compressedPath),
                    escapeshellarg($filePath)
                );

                // Execute the command
                exec($cmd . ' 2>&1', $output, $returnVar);

                // Check if compression was successful
                if ($returnVar === 0 && file_exists($compressedPath)) {
                    // Replace original with compressed file
                    // unlink($filePath);
                    rename($compressedPath, $filePath);
                } else {

                    // Proceed with original file instead of failing
                    return response()->json([
                        'message' => 'PDF uploaded successfully (compression failed, using original file)!',
                        'file_url' => asset("storage/{$path}"),
                    ]);
                }
            }

            // Return success response
            return response()->json([
                'message' => 'PDF uploaded successfully!',
                'file_url' => asset("storage/{$path}"),
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'error' => 'PDF processing failed',
                'details' => $e->getMessage(),
            ], 500);
        }

    }

}
