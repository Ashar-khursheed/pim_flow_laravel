<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Models\TransactionLog;
use App\Models\Product;
use App\Models\ProductAccessory;
use App\Models\AccessoryItem;

class ImportProductAccessoryJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

	protected $header;
	protected $chunk;
	protected $userId;
	protected $accessoriesFileFormatArray;

	/**
	 * Create a new job instance.
	 */
	public function __construct(array $data)
	{
		$this->header = $data['header'];
		$this->chunk = $data['chunk'];
		$this->userId = $data['userId'];
		$this->accessoriesFileFormatArray = $data['fileFormatArray'];
	}

	/**
	 * Execute the job.
	 */
	public function handle()
	{
		/* Find transaction log */
		$log = TransactionLog::where('identifier', $this->batch()->id)->first();

		if (!$log) {
			Log::error('Transaction log not found for batch ID: ' . $this->batch()->id);
			return;
		}

		/* Initialize counters and arrays */
		$descArray = json_decode($log->description, true) ?? [
			"Errors" => [],
			"Success Count" => 0,
			"Failed Count" => 0
		];

		$previousSuccessCount = $descArray["Success Count"] ?? 0;
		$previousFailedCount = $descArray["Failed Count"] ?? 0;

		/* Ensure arrays exist */
		if (!isset($descArray["Errors"]) || !is_array($descArray["Errors"])) {
			$descArray["Errors"] = [];
		}

		$errorArray = [];
		$success = 0;
		$failed = 0;
		$groupedAccessory = [];

		/* Pre-load data for validation - BATCH OPTIMIZATION */
		$productIds = array_unique(array_filter(array_column($this->chunk, 0))); /* Assuming first column is product_id */
		$productAccessoryIds = array_unique(array_filter(array_map(function($row) {
			/* Extract product_accessory_id from row based on header index */
			$accessoryIdIndex = array_search('product_accessory_id', $this->header);
			return $accessoryIdIndex !== false ? ($row[$accessoryIdIndex] ?? null) : null;
		}, $this->chunk)));

		/* Load existing products */
		$existingProducts = Product::whereIn('id', $productIds)
			->pluck('id')
			->flip()
			->toArray();

		/* Load existing accessories grouped by product_id */
		$existingAccessories = ProductAccessory::whereIn('product_id', $productIds)
			->get(['id', 'product_id', 'name'])
			->groupBy('product_id');

		/* Load existing accessory types grouped by product_accessory_id */
		$existingAccessoryTypes = AccessoryItem::whereIn('product_accessory_id', $productAccessoryIds)
			->get(['id', 'product_accessory_id', 'name'])
			->groupBy('product_accessory_id');

		/* Process each row */
		foreach ($this->chunk as $rowIndex => $row) {
			$rowData = [];
			$rowError = [];

			/* Validate row structure */
			if (count($this->header) !== count($row)) {
				$rowError[] = 'The data in this row is not compatible for import.';
				$errorArray[] = [
					"Row Number" => $rowIndex + 2 + $previousSuccessCount + $previousFailedCount,
					"Error" => implode(' | ', $rowError)
				];
				$failed++;
				continue;
			}

			/* Combine header with row data */
			$rowData = array_combine($this->header, $row);

			/* Extract variables dynamically */
			foreach ($this->accessoriesFileFormatArray as $headerKey => $variableName) {
				$$variableName = isset($rowData[$headerKey]) ? trim($rowData[$headerKey]) : null;
			}

			/* Required field validation */
			if (empty($product_id)) {
				$rowError[] = 'Product ID is missing.';
			}
			if (empty($accessory_name)) {
				$rowError[] = 'Accessory Name is missing.';
			}
			if (!isset($is_approved) || $is_approved === '') {
				$rowError[] = 'Is Approved is missing.';
			}
			if (!isset($is_required) || $is_required === '') {
				$rowError[] = 'Is Required is missing.';
			}
			if (empty($accessory_type)) {
				$rowError[] = 'Accessory Type is missing.';
			}
			if (!isset($price) || $price === '') {
				$rowError[] = 'Price is missing.';
			}
			if (!isset($cost_price) || $cost_price === '') {
				$rowError[] = 'Cost Price is missing.';
			}

			/* If basic validation failed, skip further checks */
			if (!empty($rowError)) {
				$errorArray[] = [
					"Row Number" => $rowIndex + 2 + $previousSuccessCount + $previousFailedCount,
					"Error" => implode(' | ', $rowError),
				];
				$failed++;
				continue;
			}

			/* Validate product exists */
			if (!isset($existingProducts[$product_id])) {
				$rowError[] = "Product ID: {$product_id} does not exist.";
				$errorArray[] = [
					"Row Number" => $rowIndex + 2 + $previousSuccessCount + $previousFailedCount,
					"Error" => implode(' | ', $rowError),
				];
				$failed++;
				continue;
			}

			/* Check duplicate ProductAccessory in memory */
			if (isset($existingAccessories[$product_id])) {
				$duplicate = $existingAccessories[$product_id]->first(function ($item) use ($accessory_name, $product_accessory_id) {
					return $item->name === $accessory_name && $item->id != $product_accessory_id;
				});

				if ($duplicate) {
					$rowError[] = "Accessory name '{$accessory_name}' already exists for product ID: {$product_id} (existing accessory ID: {$duplicate->id})";
				}
			}

			/* Check duplicate AccessoryType in memory */
			if ($product_accessory_id && isset($existingAccessoryTypes[$product_accessory_id])) {
				$duplicate = $existingAccessoryTypes[$product_accessory_id]->first(function ($item) use ($accessory_type, $accessory_type_id) {
					return $item->name === $accessory_type && $item->id != $accessory_type_id;
				});

				if ($duplicate) {
					$rowError[] = "Accessory type '{$accessory_type}' already exists for product accessory ID: {$product_accessory_id} (existing type ID: {$duplicate->id})";
				}
			}

			/* If any errors were found, log them */
			if (!empty($rowError)) {
				$errorArray[] = [
					"Row Number" => $rowIndex + 2 + $previousSuccessCount + $previousFailedCount,
					"Error" => implode(' | ', $rowError),
				];
				$failed++;
				continue;
			}

			/* Group data by accessory name */
			$accessoryNameKey = $product_id . '|' . strtolower(trim($accessory_name));

			if (!isset($groupedAccessory[$accessoryNameKey])) {
				$groupedAccessory[$accessoryNameKey]['accessoryName'] = [
					'product_id' => $product_id,
					'product_accessory_id' => $product_accessory_id ?? null,
					'name' => $accessory_name,
					'is_approved' => $is_approved,
					'is_required' => $is_required,
					'approved_by' => $is_approved ? $this->userId : null,
				];
				$groupedAccessory[$accessoryNameKey]['accessoryTypes'] = [];
			}

			/* Add accessory type to group */
			$groupedAccessory[$accessoryNameKey]['accessoryTypes'][] = [
				'accessory_type_id' => $accessory_type_id ?? null,
				'name' => $accessory_type,
				'price' => $price,
				'cost_price' => $cost_price,
			];

			$success++;
		}

		/* Begin database transaction */
		DB::beginTransaction();

		try {
			$now = now();

			/* Process grouped accessories */
			foreach ($groupedAccessory as $group) {
				$accessoryData = $group['accessoryName'];
				$accessoryTypes = $group['accessoryTypes'];

				$productAccessoryId = $accessoryData['product_accessory_id'];

				/* Update or create ProductAccessory */
				if ($productAccessoryId) {
					/* Update existing accessory */
					ProductAccessory::where('id', $productAccessoryId)->update([
						'name' => $accessoryData['name'],
						'isapproved' => $accessoryData['is_approved'],
						'isRequired' => $accessoryData['is_required'],
						'approved_by' => $accessoryData['approved_by'],
						'updated_by' => $this->userId,
						'updated_at' => $now,
					]);

					$productAccessory = ProductAccessory::find($productAccessoryId);
				} else {
					/* Create new accessory */
					$productAccessory = ProductAccessory::create([
						'product_id' => $accessoryData['product_id'],
						'name' => $accessoryData['name'],
						'isapproved' => $accessoryData['is_approved'],
						'isRequired' => $accessoryData['is_required'],
						'approved_by' => $accessoryData['approved_by'],
						'created_by' => $this->userId,
						'updated_by' => $this->userId,
						'created_at' => $now,
						'updated_at' => $now,
					]);
				}

				/* Process accessory types */
				$updatedTypeIds = [];

				foreach ($accessoryTypes as $typeData) {
					$accessoryTypeId = $typeData['accessory_type_id'];

					if ($accessoryTypeId) {
						/* Update existing type */
						AccessoryItem::where('id', $accessoryTypeId)
							->where('product_accessory_id', $productAccessory->id)
							->update([
								'name' => $typeData['name'],
								'price' => $typeData['price'],
								'cost_price' => $typeData['cost_price'],
								'updated_at' => $now,
							]);

						$updatedTypeIds[] = $accessoryTypeId;
					} else {
						/* Create new type */
						$newType = AccessoryItem::create([
							'product_accessory_id' => $productAccessory->id,
							'name' => $typeData['name'],
							'price' => $typeData['price'],
							'cost_price' => $typeData['cost_price'],
							'created_at' => $now,
							'updated_at' => $now,
						]);

						$updatedTypeIds[] = $newType->id;
					}
				}

				/* Delete accessory types not in the import (optional - uncomment if needed) */
				if (!empty($updatedTypeIds)) {
					AccessoryItem::where('product_accessory_id', $productAccessory->id)
						->whereNotIn('id', $updatedTypeIds)
						->delete();
				}
			}

			/* Update transaction log */
			$descArray["Success Count"] += $success;
			$descArray["Failed Count"] += $failed;
			$descArray["Errors"] = array_merge($descArray["Errors"], $errorArray);

			$log->update([
				'description' => json_encode($descArray),
			]);

			DB::commit();

		} catch (\Throwable $e) {
			DB::rollBack();

			Log::error('Exception in ImportProductAccessoryJob', [
				'batch_id' => $this->batch()->id,
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'message' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			]);

			/* Update transaction log with error */
			if ($log) {
				$errorMessage = "Job execution failed: " . $e->getMessage();
				$descArray["Errors"][] = [
					"General Error" => $errorMessage,
					"File" => $e->getFile(),
					"Line" => $e->getLine(),
				];
				$descArray["Failed Count"] += count($this->chunk);

				$log->update([
					'description' => json_encode($descArray),
					'status' => 'failed'
				]);
			}

			/* Rethrow to let Laravel handle the job failure */
			throw $e;
		}
	}

	/**
	 * Handle a job failure.
	 */
	public function failed(\Throwable $exception): void
	{
		$log = TransactionLog::where('identifier', $this->batch()->id)->first();

		if (!$log) {
			Log::error('Transaction log not found for batch: ' . $this->batch()->id);
			return;
		}

		$jobName = class_basename($this);

		$errorDetails = [
			'job' => $jobName,
			'message' => $exception->getMessage(),
			'file' => $exception->getFile(),
			'line' => $exception->getLine(),
			'trace' => $exception->getTraceAsString(),
		];

		Log::error("{$jobName} failed", $errorDetails);

		$description = json_decode($log->description, true) ?? [];

		$description['Success Count'] = $description['Success Count'] ?? 0;
		$description['Failed Count'] = $description['Failed Count'] ?? 0;
		$description['Errors'] = $description['Errors'] ?? [];

		$description['Errors'][] = [
			'Row Number' => 'N/A',
			'Job' => $jobName,
			'Error' => $errorDetails['message'],
			'File' => $errorDetails['file'],
			'Line' => $errorDetails['line'],
		];

		TransactionLog::where('id', $log->id)->update([
			'status' => 'Failed',
			'description' => json_encode($description, JSON_UNESCAPED_UNICODE),
		]);
	}
}