<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait HandlesImageUpload
{
	/**
	 * Get image URL - either use existing AWS URL or upload from external URL
	 *
	 * @param string|null $image Image URL
	 * @param string $path S3 storage path (e.g., 'products', 'attribute/multiple_images')
	 * @return string|null
	 */
	protected function getImageURL(?string $image, string $path = 'products'): ?string
	{
		if (empty($image)) {
			return null;
		}

		/* If already on our AWS S3, return as-is */
		if (Str::startsWith($image, env('AWS_URL'))) {
			return $image;
		}

		/* If it's an external URL, upload it to our S3 */
		if (Str::startsWith($image, ['http://', 'https://'])) {
			$uploadedImage = $this->uploadImageFromURL($image, $path);
			if ($uploadedImage) {
				return $uploadedImage;
			}
			Log::warning("Failed to upload image from URL", [
				'url' => $image,
				'path' => $path
			]);
			return null;
		}

		/* Invalid URL format */
		Log::warning("Invalid image URL format", ['url' => $image]);
		return null;
	}

	/**
	 * Upload image from external URL to S3 and convert to WebP
	 *
	 * @param string|null $url External image URL
	 * @param string $path S3 storage path (e.g., 'products', 'attribute/multiple_images')
	 * @return string|null Uploaded image URL on S3
	 */
	protected function uploadImageFromURL(?string $url, string $path = 'products'): ?string
	{
		/* Validate URL */
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			Log::error('Invalid URL provided', ['url' => $url]);
			return null;
		}

		/* Fetch image content */
		$imageContents = @file_get_contents($url);

		if ($imageContents === false || empty($imageContents)) {
			Log::error('Failed to download image from URL or content is empty', ['url' => $url]);
			return null;
		}

		/* Sanitize file name */
		$fileNameWithQuery = basename(parse_url($url, PHP_URL_PATH));
		$fileName = preg_replace('/\?.*/', '', $fileNameWithQuery);
		$fileBaseName = pathinfo($fileName, PATHINFO_FILENAME);
		$fileExtension = 'webp'; /* Convert all to WebP */

		if (empty($fileBaseName)) {
			$fileBaseName = 'image_' . time() . '_' . Str::random(8);
		}

		try {
			/* Create image resource from content */
			$image = imagecreatefromstring($imageContents);

			if (!$image) {
				Log::error('Failed to create image from URL', ['url' => $url]);
				return null;
			}

			/* Ensure image is in Truecolor format */
			if (imageistruecolor($image) === false) {
				imagepalettetotruecolor($image);
			}

			/* Build S3 path */
			$s3Disk = Storage::disk('s3');
			$storagePath = env('STORAGE_ENV') . "/{$path}/{$fileBaseName}.{$fileExtension}";

			/* Convert to WebP and upload to S3 */
			ob_start();
			imagewebp($image, null, 90); /* 90 quality */
			$webpData = ob_get_clean();

			$s3Disk->put($storagePath, $webpData);
			$imageUrl = $s3Disk->url($storagePath);

			/* Clean up */
			imagedestroy($image);
			return $imageUrl;

		} catch (\Exception $e) {
			Log::error('S3 Upload Error', [
				'url' => $url,
				'path' => $path,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			]);
			return null;
		}
	}

	/**
	 * Upload multiple images from URLs
	 *
	 * @param array $urls Array of image URLs
	 * @param string $path S3 storage path
	 * @return array Array of uploaded S3 URLs
	 */
	protected function uploadMultipleImagesFromURLs(array $urls, string $path = 'products'): array
	{
		$uploadedUrls = [];

		foreach ($urls as $url) {
			if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
				$uploadedUrl = $this->getImageURL($url, $path);

				if ($uploadedUrl) {
					$uploadedUrls[] = $uploadedUrl;
				}
			}
		}

		return $uploadedUrls;
	}
}