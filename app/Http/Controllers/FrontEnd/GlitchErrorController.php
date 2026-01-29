<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\BaseController;
use App\Models\FrontEnd\GlitchError;
use Illuminate\Http\Request;
use App\Jobs\SendGlitchErrorReportMailJob;

use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

class GlitchErrorController extends BaseController
{
	/**
	 * @OA\Get(
	 *     path="/api/frontend/glitch-errors",
	 *     summary="Get all glitch errors with pagination and filters",
	 *     tags={"FrontEnd-GlitchErrors"},
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Response(response=200, description="List retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function index(Request $request)
	{
		$recordsQuery = GlitchError::orderBy('id', 'desc');

		if ($request->filled('page') && $request->filled('length')) {
			$length = (int) $request->input('length');
			$page = (int) $request->input('page');

			$totalRecords = (clone $recordsQuery)->count();
			$totalPages = (int) ceil($totalRecords / $length);

			if ($page > $totalPages && $totalPages > 0) {
				$page = 1;
			}

			$records = $recordsQuery
			->offset(($page - 1) * $length)
			->limit($length)
			->get();
		} else {
			$records = $recordsQuery->get();
			$totalRecords = $records->count();
			$totalPages = 1;
		}

		return response()->json([
			'success' => true,
			'message' => __('msg_rec_list'),
			'data' => $records,
			'total_pages' => $totalPages,
			'total_records' => $totalRecords,
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/glitch-errors",
	 *     summary="Create a new glitch error",
	 *     tags={"FrontEnd-GlitchErrors"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"email", "description"},
	 *                 @OA\Property(property="email", type="string", format="email", example="user@example.com"),
	 *                 @OA\Property(property="mobile_number", type="string", example="971500000000"),
	 *                 @OA\Property(property="description", type="string", example="Page not loading properly."),
	 *                 @OA\Property(property="device", type="string", example="Android."),
	 *                 @OA\Property(property="images[]", type="array", @OA\Items(type="string", format="binary"))
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Created successfully", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function store(Request $request)
	{
		$request->validate([
			'email' => 'required|string|email:strict',
			'mobile_number' => 'nullable|string',
			'description' => 'required|string|min:10|max:250',
			'images' => 'nullable|array',
			'images.*' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:2048',
		]);

		/* Upload media files */
		$uploadedImages = [];
		if ($request->hasFile('images')) {
			foreach ($request->file('images') as $index => $imageFile) {
				$tempRequest = new \Illuminate\Http\Request();
				$tempRequest->files->set('product_image_single', $imageFile);

				$uploadedUrl = uploadImageToWebpS3FromFile(
					$tempRequest,
					'product_image_single',
					env('STORAGE_ENV') . '/glitch-errors/images'
				);

				if ($uploadedUrl) {
					$uploadedImages[] = $uploadedUrl;
				}
			}
		}

		$images = json_encode($uploadedImages);
		$record = GlitchError::create([
			'email' => $request->email,
			'mobile_number' => $request->mobile_number,
			'description' => $request->description,
			'device' => $request->device,
			'images' => $images,
		]);

		$batch = Bus::batch([])->name('Glitch Errors')->dispatch();

		$batch->options['queue'] = config('app.website') . '_GLITCH';
		$batch->add(new SendGlitchErrorReportMailJob([
			'recordId' => $record->id
		]));

		return response()->json([
			'success' => true,
			'message' => 'Data saved successfully',
			'data' => $record
		], 201);
	}

	/**
	 * Display the specified resource.
	 */
	public function show(GlitchError $glitchError)
	{
		//
	}

	/**
	 * Update the specified resource in storage.
	 */
	public function update(Request $request, GlitchError $glitchError)
	{
		//
	}

	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy(GlitchError $glitchError)
	{
		//
	}
}
