<?php

namespace App\Http\Controllers;

use App\Models\VendorDocument;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class VendorDocumentController extends Controller
{
	/**
	 * @OA\Post(
	 *     path="/api/vendors/{vendor_id}/documents",
	 *     summary="Upload Vendor Document",
	 *     description="Uploads a document for the specified vendor.",
	 *     tags={"Vendors"},
	 *     @OA\Parameter(
	 *         name="vendor_id",
	 *         in="path",
	 *         description="ID of the vendor",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"name", "document"},
	 *                 @OA\Property(property="name", type="string", example="Business License"),
	 *                 @OA\Property(property="document", type="string", format="binary")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request, $vendor_id)
	{
		$request->validate([
			'name' => 'required|string|max:255',
			'document' => 'required|file|max:10240|mimes:png,jpg,jpeg,webp,pdf,xls,xlsx,mp4,mkv,csv,txt'
		]);

		$file = $request->file('document');
		$extension = strtolower($file->getClientOriginalExtension());
		$envPath = env('STORAGE_ENV') . '/vendors/documents';

		if (in_array($extension, ['png', 'jpg', 'jpeg', 'webp'])) {
			$url = $this->uploadImageToS3($file, $request->input('name'), $envPath);
			$type = 'image';
		} elseif (in_array($extension, ['pdf', 'xls', 'xlsx', 'csv', 'txt'])) {
			$url = $this->uploadGenericFileToS3($file, $request->input('name'), $envPath);
			$type = 'file';
		} elseif (in_array($extension, ['mp4', 'mkv'])) {
			$url = $this->uploadGenericFileToS3($file, $request->input('name'), $envPath);
			$type = 'video';
		} else {
			return response()->json([
				'success' => false,
				'message' => 'Invalid file type'
			]);
		}

		$document = VendorDocument::create([
			'vendor_id' => $vendor_id,
			'name' => $request->input('name'),
			'type' => $type,
			'url' => $url,
			'created_by' => auth()->id()
		]);

		return response()->json([
			'success' => true,
			'message' => 'Document uploaded successfully',
			'data' => $document
		]);
	}

	private function uploadImageToS3($file, string $baseName, string $pathPrefix)
	{
		try {
			$image = imagecreatefromstring(file_get_contents($file->getRealPath()));
			if (!$image) {
				Log::error('Failed to create image from file.');
				return null;
			}

			if (!imageistruecolor($image)) imagepalettetotruecolor($image);

			ob_start();
			imagewebp($image);
			$webpData = ob_get_clean();
			imagedestroy($image);

			$filename = pathinfo($baseName, PATHINFO_FILENAME);
			$uniqueName = $filename . '_' . time() . '.webp';
			$path = "{$pathPrefix}/{$uniqueName}";

			Storage::disk('s3')->put($path, $webpData);
			return Storage::disk('s3')->url($path);
		} catch (\Exception $e) {
			Log::error('uploadImageToS3 error: ' . $e->getMessage());
			return null;
		}
	}

	private function uploadGenericFileToS3($file, string $baseName, string $pathPrefix)
	{
		try {
			$extension = $file->getClientOriginalExtension();
			$filename = pathinfo($baseName, PATHINFO_FILENAME);
			$uniqueName = $filename . '_' . time() . '.' . $extension;
			$path = "{$pathPrefix}/{$uniqueName}";

			Storage::disk('s3')->put($path, file_get_contents($file->getRealPath()));
			return Storage::disk('s3')->url($path);
		} catch (\Exception $e) {
			Log::error('uploadGenericFileToS3 error: ' . $e->getMessage());
			return null;
		}
	}

	/**
	 * Display vendor documents by vendor ID, optionally filtered by type.
	 *
	 * @OA\Get(
	 *     path="/api/vendors/{vendor_id}/documents",
	 *     summary="Get Vendor Documents",
	 *     description="Returns a list of documents uploaded by a vendor, optionally filtered by type (image, file, video).",
	 *     tags={"Vendors"},
	 *     @OA\Parameter(name="vendor_id", in="path", required=true, description="ID of the vendor", @OA\Schema(type="integer", example=1)),
	 *     @OA\Parameter(name="type", in="query", required=true, description="Filter by document type: image, file, or video", @OA\Schema(type="string", enum={"image", "file", "video"})),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show(Request $request, $vendor_id)
	{
		$validated = $request->validate([
			'type'      => 'required|in:image,file,video',
		]);

		if (!Vendor::where('id', $vendor_id)->exists()) {
			return response()->json([
				'success' => false,
				'message' => 'Vendor not found'
			]);
		}

		$type = $request->input('type');

		$documents = VendorDocument::with('creator:id,first_name,last_name')->where('vendor_id', $vendor_id)
		->where('type', $type)
		->orderByDesc('created_at')
		->get();

		$documents->transform(function ($record) {
			$record->created_by = $record->creator->name;
			unset($record->creator);

			return $record;
		});

		return response()->json([
			'success' => true,
			'data'    => $documents
		]);
	}

	/**
	 * Update the specified resource in storage.
	 */
	public function update(Request $request, VendorDocument $vendorDocument)
	{
		//
	}

	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy(VendorDocument $vendorDocument)
	{
		//
	}
}
