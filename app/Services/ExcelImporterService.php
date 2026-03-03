<?php

namespace App\Services;

use App\Repository\ExcelRepository;
use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\TransactionLog;
use App\Models\AccessoryItem;
use Throwable;

class ExcelImporterService
{
	protected $excelRepo;

	public function __construct(ExcelRepository $excelRepo)
	{
		$this->excelRepo = $excelRepo;
	}

	public function processExcelImport($file, array $fileFormatArray, string $module, string $queue, string $batchName, string $jobClass)
	{
		$realPath = $file->getRealPath();

		$worksheetInfo = $this->excelRepo->getAllWorksheetInfo($realPath);
		if (empty($worksheetInfo)) {
			throw new \Exception("The Excel file has no worksheet.");
		}

		$worksheetName = $worksheetInfo[0]['worksheetName'];
		$totalRows = $worksheetInfo[0]['totalRows'];
		$lastColumnLetter = $worksheetInfo[0]['lastColumnLetter'];

		$headerRow = $this->excelRepo->loadExcelFileData($realPath, $worksheetName, 1, 1, $lastColumnLetter)[0] ?? [];

		if (empty($headerRow)) {
			throw new \Exception("The Excel file does not contain a valid header.");
		}

		$header = array_map('trim', $headerRow);

		$requiredHeaders = array_keys($fileFormatArray);
		if ($missing = array_diff($requiredHeaders, $header)) {
			throw new \Exception('Missing required column(s): ' . implode(', ', $missing));
		}

		$userRole = auth()->user()->getRoleNames()->first();
		if ($module == 'Product') {
			if (in_array($userRole, ['Content Writing Manager', 'Content Writer'])) {
				$rowsPerChunk = 5;
			} else {
				$rowsPerChunk = 50;
			}
		} else {
			$rowsPerChunk = 100;
		}

		$totalRecords = $totalRows - 1;

		if ($totalRecords == 0) {
			throw new \Exception("Excel file does not contain any data rows.");
		}

		if ($totalRecords > 2000 && in_array($module, ['Product', 'Product Attribute', 'Product Supplier'])) {
			throw new \Exception("The uploaded Excel file contains more than 2000 records.");
		}

		if ($totalRecords > 10000) {
			throw new \Exception("The uploaded Excel file contains more than 10000 records.");
		}

		if ($totalRecords <= $rowsPerChunk) {
			$chunkStarts = [2];
		} else {
			$chunkStarts = range(2, $totalRows, $rowsPerChunk);
		}

		$action = str_contains($module, ' Translation') ? "Import_Translation" : "Import";

		/* Determine if this module needs change_obj tracking */
		$needsChangeObj = in_array($module, ['Product Accessory']);

		$batch = Bus::batch([])->before(function (Batch $batch) use ($module, $totalRecords, $action, $needsChangeObj) {
			$logData = [
				'module' => $module,
				'action' => $action,
				'identifier' => $batch->id,
				'status' => 'In-progress',
				'description' => json_encode([
					"Total Count" => $totalRecords,
					"Success Count" => 0,
					"Failed Count" => 0,
					"Errors" => []
				], JSON_UNESCAPED_UNICODE),
				'created_by' => auth()->id(),
				'created_at' => now()
			];

			/* Initialize change_obj only for specific modules */
			if ($needsChangeObj) {
				$logData['change_obj'] = json_encode([
					'processed_product_accessory_ids' => [],
					'processed_accessory_type_ids' => []
				]);
			}

			TransactionLog::create($logData);
		})->then(function (Batch $batch) use ($module) {
			/* Cleanup logic only for Product Accessory module */
			if ($module === 'Product Accessory') {
				/* Get transaction log */
				$log = TransactionLog::where('identifier', $batch->id)->first();

				if (!$log) {
					return;
				}

				/* Get processed IDs from change_obj */
				$changeObj = json_decode($log->change_obj, true) ?? [];
				$processedProductAccessoryIds = $changeObj['processed_product_accessory_ids'] ?? [];
				$processedAccessoryTypeIds = $changeObj['processed_accessory_type_ids'] ?? [];

				if (empty($processedProductAccessoryIds)) {
					Log::info('No product accessories processed, skipping cleanup');
					return;
				}

				DB::beginTransaction();

				try {
					/* Delete accessory types that belong to processed accessories but were NOT in the import file */
					$deletedCount = AccessoryItem::whereIn('product_accessory_id', $processedProductAccessoryIds)
					->whereNotIn('id', $processedAccessoryTypeIds)
					->delete();

					Log::info('Cleanup completed successfully', [
						'batch_id' => $batch->id,
						'deleted_accessory_types' => $deletedCount
					]);

					/* Update transaction log with cleanup results */
					$description = json_decode($log->description, true) ?? [];
					$description['Cleanup'] = [
						'deleted_accessory_types' => $deletedCount,
						'completed_at' => now()->toDateTimeString()
					];

					$log->update([
						'description' => json_encode($description, JSON_UNESCAPED_UNICODE)
					]);

					DB::commit();

				} catch (\Throwable $e) {
					DB::rollBack();

					Log::error('Cleanup failed', [
						'batch_id' => $batch->id,
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString()
					]);

					/* Update log with cleanup error */
					$description = json_decode($log->description, true) ?? [];
					$description['Cleanup Error'] = [
						'message' => $e->getMessage(),
						'file' => $e->getFile(),
						'line' => $e->getLine()
					];

					$log->update([
						'description' => json_encode($description, JSON_UNESCAPED_UNICODE)
					]);
				}
			}
		})->catch(function (Batch $batch, Throwable $e) {
			TransactionLog::where('identifier', $batch->id)->update([
				'status' => 'Failed',
				'description' => json_encode([
					"Total Count" => 0,
					"Success Count" => 0,
					"Failed Count" => 0,
					"Errors" => [
						"Error: " . $e->getMessage(),
						"File: " . $e->getFile(),
						"Line: " . $e->getLine()
					]
				], JSON_UNESCAPED_UNICODE)
			]);
		})->finally(function (Batch $batch) {
			TransactionLog::where('identifier', $batch->id)->update(['status' => 'Completed']);
		})->name($batchName)->dispatch();

		/* Add jobs to batch after creation */
		foreach ($chunkStarts as $startRow) {
			$endRow = min($startRow + $rowsPerChunk - 1, $totalRows);
			$chunkData = $this->excelRepo->loadExcelFileData($realPath, $worksheetName, $startRow, $endRow, $lastColumnLetter);

			if (!empty($chunkData)) {
				Log::info($module . ' Job Creation:', [
					'startRow' => $startRow,
					'endRow' => $endRow,
					'lastColumnLetter' => $lastColumnLetter
				]);

				$batch->options['queue'] = $queue;
				$batch->add(new $jobClass([
					'fileFormatArray' => $fileFormatArray,
					'header' => $header,
					'chunk' => $chunkData,
					'userId' => auth()->id(),
					'userRole' => auth()->user()->getRoleNames()->first() ?? null,
				]));
			}
		}
	}
}