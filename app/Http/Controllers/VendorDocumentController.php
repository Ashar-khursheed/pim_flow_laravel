<?php

namespace App\Http\Controllers;

use App\Models\VendorDocument;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use Illuminate\Support\Str;

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
			'document' => 'required|file|max:51200|mimes:png,jpg,jpeg,webp,pdf,xls,xlsx,mp4,mkv,csv,txt,doc,docx,msg',
		]);

		$file = $request->file('document');
		$extension = strtolower($file->getClientOriginalExtension());
		$envPath = env('STORAGE_ENV') . '/vendors/documents';

		if (in_array($extension, ['png', 'jpg', 'jpeg', 'webp'])) {
			$url = $this->uploadImageToS3($file, $request->input('name'), $envPath);
			$type = 'image';
		} elseif (in_array($extension, ['pdf', 'xls', 'xlsx', 'csv', 'txt', 'doc', 'docx'])) {
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

		if ($documents->isEmpty()) {
			return response()->json([
				'success' => false,
				'message' => 'No documents found.',
			]);
		}

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
	 * @OA\Get(
	 *     path="/api/vendors/{vendor_id}/documents/download",
	 *     summary="Download Vendor Documents as ZIP",
	 *     description="Downloads all vendor documents as a ZIP file, optionally filtered by type (image, file, video).",
	 *     tags={"Vendors"},
	 *     @OA\Parameter(name="vendor_id", in="path", required=true, description="Vendor ID", @OA\Schema(type="integer")),
	 *     @OA\Parameter(name="type", in="query", required=false, description="Document type: image, file, or video", @OA\Schema(type="string", enum={"image", "file", "video"})),
	 *     @OA\Response(
	 *         response=200,
	 *         description="ZIP file containing product media",
	 *         @OA\MediaType(
	 *             mediaType="application/zip",
	 *             @OA\Schema(type="string", format="binary")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Product not found",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="Product not found")
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function downloadMediaZip(Request $request, $vendor_id)
	{
		$request->validate([
			'type' => 'nullable|in:image,file,video',
		]);

		if (!Vendor::where('id', $vendor_id)->exists()) {
			return response()->json([
				'success' => false,
				'message' => 'Vendor not found',
			], 404);
		}

		$query = VendorDocument::where('vendor_id', $vendor_id);
		if ($request->has('type')) {
			$query->where('type', $request->input('type'));
		}
		$documents = $query->get();

		if ($documents->isEmpty()) {
			return response()->json([
				'success' => false,
				'message' => 'No documents found.',
			], 404);
		}

		$tempDir = storage_path('app/vendor_docs_temp');
		if (!file_exists($tempDir)) {
			mkdir($tempDir, 0755, true);
		}

		$zipFileName = 'vendor_' . $vendor_id . '_documents_' . Str::random(8) . '.zip';
		$zipFilePath = $tempDir . '/' . $zipFileName;

		$zip = new ZipArchive;
		if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
			foreach ($documents as $doc) {
				try {
					$url = $doc->url;
					$filename = basename(parse_url($url, PHP_URL_PATH));
					$typeDir = $doc->type;

					if (Str::startsWith($url, [env('AWS_URL'), env('AWS_CACHE_URL')])) {

						$filePath = Str::after($url, env('AWS_URL') . '/');

						if ($filePath === $url) {
							$filePath = Str::after($url, env('AWS_CACHE_URL') . '/');
						}

						if (Storage::disk('s3')->exists($filePath)) {
							$stream = Storage::disk('s3')->readStream($filePath);
							$zip->addFromString("{$typeDir}/{$filename}", stream_get_contents($stream));
							fclose($stream);
						}
					}
				} catch (\Exception $e) {
					Log::error("ZIP error: " . $e->getMessage());
				}
			}
			$zip->close();
		} else {
			return response()->json([
				'success' => false,
				'message' => 'Failed to create ZIP file.',
			], 500);
		}

		return response()->download($zipFilePath)->deleteFileAfterSend(true);
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
