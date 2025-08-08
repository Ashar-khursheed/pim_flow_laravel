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
	 *     summary="Download Laravel log by date",
	 *     description="Downloads the log file for a given date in YYYY-MM-DD format",
	 *     @OA\Parameter(name="date", in="query", required=true, description="Date of the log file (YYYY-MM-DD)", @OA\Schema(type="string", format="date")),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function downloadLog(Request $request)
	{
		$date = $request->query('date');

		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
			return response()->json(['message' => 'Invalid date format. Use YYYY-MM-DD.'], 422);
		}

		$path = storage_path("logs/laravel-{$date}.log");

		if (!File::exists($path)) {
			return response()->json(['message' => 'Log file does not exist.'], 404);
		}

		return response()->download($path, "laravel-{$date}.log", [
			'Content-Type' => 'text/plain',
		]);
	}
}

