<?php
namespace App\Http\Controllers\FrontEnd;


use App\Http\Controllers\Controller;
use App\Models\FrontEnd\Inquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

use App\Jobs\Inquiry\InquiryMailJob;

class InquiryController extends Controller
{
	/**
	 * @OA\Post(
	 *     path="/api/frontend/inquiries",
	 *     tags={"FrontEnd Inquiries"},
	 *     summary="Submit a new inquiry",
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"full_name","phone","email","company_name","restaurant_type"},
	 *                 @OA\Property(property="full_name", type="string", example="John Smith"),
	 *                 @OA\Property(property="phone", type="string", example="+1 (234) 567-8900"),
	 *                 @OA\Property(property="email", type="string", example="you@example.com"),
	 *                 @OA\Property(property="company_name", type="string", example="Bella's Italian Bistro"),
	 *                 @OA\Property(property="restaurant_type", type="string", example="Italian"),
	 *                 @OA\Property(property="lead_type", type="string", example="Web Form"),
	 *                 @OA\Property(property="lead_source", type="string", example="Google"),
	 *                 @OA\Property(property="landing_page", type="string", example="Starting a Restaurant"),
	 *                 @OA\Property(
	 *                     property="files",
	 *                     type="array",
	 *                     @OA\Items(type="string", format="binary")
	 *                 ),
	 *                 @OA\Property(property="notes", type="string", example="Attach menu if available")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Inquiry created"),
	 *     @OA\Response(response=422, description="Validation error")
	 * )
	 */
	public function store(Request $request): JsonResponse
	{
		try {

			$validated = $request->validate([
				'full_name' => 'required|string|max:255',
				'phone' => 'required|string|max:50',
				'email' => 'required|email:strict|max:255',
				'company_name' => 'required|string|max:255',
				'restaurant_type' => 'required|string|max:255',
				'lead_type' => 'required|string|max:255',
				'lead_source' => 'required|string|max:255',
				'landing_page' => 'required|string|max:255',
				'notes' => 'nullable|string',
				'files' => 'nullable',
				'files.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:10240',
			]);

			$uploadedFiles = [];

			// Get files from request
			$files = $request->file('files');

			if ($files) {

				// Convert single file to array for consistent processing
				if (!is_array($files)) {
					$files = [$files];
				}

				foreach ($files as $file) {
					if ($file && $file->isValid()) {
						$path = $file->store("production/inquiries", 's3');
						$title = $file->getClientOriginalName();

						$uploadedFiles[] = [
							'title' => $title,
							'path'  => Storage::disk('s3')->url($path),
						];
					}
				}
			}

			$inquiry = Inquiry::create([
				'full_name' => $validated['full_name'],
				'phone' => $validated['phone'],
				'email' => $validated['email'],
				'company_name' => $validated['company_name'],
				'restaurant_type' => $validated['restaurant_type'],
				'lead_type' => $validated['lead_type'],
				'lead_source' => $validated['lead_source'],
				'landing_page' => $validated['landing_page'],
				'notes' => $validated['notes'] ?? null,
				'files' => $uploadedFiles,
			]);

			$batch = Bus::batch([])->name('Inquiry from FrontEnd')->dispatch();
			$batch->options['queue'] = config('app.website') . '_INQ';
			$batch->add(new InquiryMailJob([
				'recordId' => $inquiry->id
			]));

			return response()->json([
				'success' => true,
				'message' => 'Thank you for your inquiry! We\'ve received your information and will get back to you shortly. Your journey with us begins now!',
				'data' => $inquiry
			], 201);

		} catch (\Illuminate\Validation\ValidationException $e) {
			return response()->json([
				'success' => false,
				'message' => 'Please check the information you provided and try again.',
				'errors' => $e->errors()
			], 422);

		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Oops! Something went wrong on our end. Please try again in a moment.',
				'error' => 'Server error occurred'
			], 500);
		}
	}

	// /**
	//  * @OA\Get(
	//  *     path="/api/frontend/inquiries/{id}",
	//  *     tags={"FrontEnd Inquiries"},
	//  *     summary="Get single inquiry",
	//  *     @OA\Parameter(
	//  *         name="id",
	//  *         in="path",
	//  *         required=true,
	//  *         @OA\Schema(type="integer")
	//  *     ),
	//  *     @OA\Response(response=200, description="Inquiry found"),
	//  *     @OA\Response(response=404, description="Not found")
	//  * )
	//  */
	// public function show($id): JsonResponse
	// {
	// 	$inquiry = Inquiry::find($id);

	// 	if (! $inquiry) {
	// 		return response()->json(['message' => 'Not found'], 404);
	// 	}

	// 	return response()->json($inquiry);
	// }

	// /**
	//  * @OA\Delete(
	//  *     path="/api/frontend/inquiries/{id}",
	//  *     tags={"FrontEnd Inquiries"},
	//  *     summary="Delete an inquiry",
	//  *     @OA\Parameter(
	//  *         name="id",
	//  *         in="path",
	//  *         required=true,
	//  *         @OA\Schema(type="integer")
	//  *     ),
	//  *     @OA\Response(response=204, description="Deleted"),
	//  *     @OA\Response(response=404, description="Not found")
	//  * )
	//  */
	// public function destroy($id): JsonResponse
	// {
	// 	$inquiry = Inquiry::find($id);

	// 	if (! $inquiry) {
	// 		return response()->json(['message' => 'Not found'], 404);
	// 	}

	// 	// Delete stored files
	// 	if (is_array($inquiry->files)) {
	// 		foreach ($inquiry->files as $file) {
	// 			$relativePath = str_replace(Storage::disk('public')->url(''), '', $file);
	// 			Storage::disk('public')->delete($relativePath);
	// 		}
	// 	}

	// 	$inquiry->delete();

	// 	return response()->json(null, 204);
	// }
}
