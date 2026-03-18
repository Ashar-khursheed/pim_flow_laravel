<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;

class LogDownloadController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/logs/download",
	 *     tags={"Auth"},
	 *     summary="Download Laravel log by name",
	 *     @OA\Parameter(name="name", in="query", required=true, @OA\Schema(type="string"), description="Log file name without extension", example="test-laravel-2025-09-06"),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function downloadLog(Request $request)
	{
		$name = $request->query('name');

		/* Validate log name is provided */
		if (!$name) {
			return response()->json(['message' => 'Log name is required.'], 422);
		}
		$path = storage_path("logs/{$name}.log");

		if (!File::exists($path)) {
			return response()->json(['message' => 'Log file does not exist.'], 404);
		}

		return response()->download($path, "{$name}.log", [
			'Content-Type' => 'text/plain',
		]);
	}
}