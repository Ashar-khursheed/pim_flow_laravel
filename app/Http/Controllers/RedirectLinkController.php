<?php

namespace App\Http\Controllers;

use App\Models\RedirectLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RedirectLinkController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/redirect-links",
     *     summary="Get list of all redirect links",
     *     tags={"Redirect Links"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of redirect links",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="from", type="string", example="/category1"),
     *                 @OA\Property(property="to", type="string", example="/category4324/22")
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        return response()->json(RedirectLink::select('from', 'to')->get());
    }

    /**
     * @OA\Post(
     *     path="/api/redirect-links",
     *     summary="Create a redirect link",
     *     tags={"Redirect Links"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"from", "to"},
     *                 @OA\Property(property="from", type="string", example="/category1"),
     *                 @OA\Property(property="to", type="string", example="/category4324/22")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Redirect created successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'from' => 'required|string|unique:redirect_links,from',
            'to'   => 'required|string',
        ]);

        $redirect = RedirectLink::create($request->only('from', 'to'));

        return response()->json([
            'message' => 'Redirect created successfully',
            'data' => $redirect
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/redirect-links/import",
     *     summary="Import redirect links from a CSV file",
     *     tags={"Redirect Links"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file"},
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="CSV file (.csv)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Redirects imported successfully"),
     *     @OA\Response(response=422, description="Invalid file upload")
     * )
     */
    public function import(Request $request)
    {
        try {
            // Validate the file is present and of the right type
            $validator = validator($request->all(), [
                'file' => 'required|file|mimes:csv,txt'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Invalid file format. Please upload a CSV file (.csv).',
                    'details' => $validator->errors()
                ], 422);
            }
            
            // Get the uploaded file
            $file = $request->file('file');
            $path = $file->getRealPath();
            
            // Open the file
            $handle = fopen($path, 'r');
            if (!$handle) {
                return response()->json(['error' => 'Could not open file'], 422);
            }
            
            $rowCount = 0;
            $successCount = 0;
            $errors = [];
            
            // Process the CSV rows
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $rowCount++;
                
                // Skip header row if present
                if ($rowCount === 1 && (strtolower($data[0]) === 'from' || strtolower($data[0]) === 'source')) {
                    continue;
                }
                
                // Check if the row has at least two columns
                if (count($data) < 2 || empty($data[0]) || empty($data[1])) {
                    $errors[] = "Row {$rowCount}: Missing 'from' or 'to' value";
                    continue;
                }
                
                // Clean data
                $from = trim($data[0]);
                $to = trim($data[1]);
                
                // Check if the redirect already exists
                $existingRedirect = RedirectLink::where('from', $from)->first();
                if ($existingRedirect) {
                    $errors[] = "Row {$rowCount}: Redirect from '{$from}' already exists";
                    continue;
                }
                
                // Create the redirect
                try {
                    RedirectLink::create([
                        'from' => $from,
                        'to'   => $to,
                    ]);
                    $successCount++;
                } catch (\Exception $e) {
                    $errors[] = "Row {$rowCount}: Failed to create redirect - " . $e->getMessage();
                }
            }
            
            fclose($handle);
            
            return response()->json([
                'message' => "{$successCount} redirects imported successfully",
                'total_rows' => $rowCount,
                'successful_imports' => $successCount,
                'errors' => $errors
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'File validation failed. Please upload a valid CSV file.',
                'details' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error importing redirect links: ' . $e->getMessage());
            return response()->json([
                'error' => 'An error occurred during import: ' . $e->getMessage(),
                'file_type' => $request->file('file') ? $request->file('file')->getMimeType() : 'unknown'
            ], 500);
        }
    }
}