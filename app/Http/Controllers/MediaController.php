<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;

class MediaController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/media",
     *     summary="Fetch all folders inside the 'production' directory from S3",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="List of folders")
     * )
     */
    public function index()
    {
        $folders = Storage::disk('s3')->directories('production');
        return response()->json(['folders' => $folders]);
    }

    /**
     * @OA\Get(
     *     path="/api/media/{folder}",
     *     summary="List all files in a specific folder inside 'production'",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="folder", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="List of files")
     * )
     */
    public function show($folder)
    {
        $files = Storage::disk('s3')->files("production/$folder");
        return response()->json(['files' => $files]);
    }

    /**
     * @OA\Post(
     *     path="/api/media",
     *     summary="Upload file to a specific folder inside 'production'",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="file", type="string", format="binary"),
     *                 @OA\Property(property="folder", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="File uploaded successfully")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file',
            'folder' => 'nullable|string'
        ]);

        $folder = $request->folder ? "production/{$request->folder}" : 'production';
        $path = $request->file('file')->store($folder, 's3');
        $url = Storage::disk('s3')->url($path);

        return response()->json(['message' => 'File uploaded successfully', 'url' => $url]);
    }

    /**
     * @OA\Delete(
     *     path="/api/media/{file}",
     *     summary="Delete file from S3 inside 'production'",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="file", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="File deleted successfully"),
     *     @OA\Response(response=404, description="File not found")
     * )
     */
    public function destroy($file)
    {
        $filePath = "production/{$file}";
        if (Storage::disk('s3')->exists($filePath)) {
            Storage::disk('s3')->delete($filePath);
            return response()->json(['message' => 'File deleted successfully']);
        }
        return response()->json(['error' => 'File not found'], 404);
    }
}
