<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

use App\Models\TransactionLog;
use App\Models\Category;
use App\Models\MeasurementType;
use App\Models\CategoryMeasurementUnitPriority;

class ImportCategoryPriorityJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

	protected $fileFormatArray;
	protected $header;
	protected $chunk;
	protected $userId;

	public function __construct($data)
	{
		$this->fileFormatArray = $data['fileFormatArray'];
		$this->header = $data['header'];
		$this->chunk = $data['chunk'];
		$this->userId = $data['userId'];
	}

	public function handle()
	{
		$log = TransactionLog::where('identifier', $this->batch()->id)->first();

		$measurementTypeIdNames = MeasurementType::pluck('name', 'id')->toArray();
		$productFamilyIdNames = Category::whereDoesntHave('children')->pluck('name', 'id')->toArray();
		$measurementTypes = MeasurementType::with('units:measurement_type_id,id,name')->get()->toArray();
		$measurementTypeIdArray = [];
		foreach ($measurementTypes as $type) {
			foreach ($type['units'] as $unit) {
				$measurementTypeIdArray[$type['id']][$unit['name']] = $unit['id'];
			}
		}

		$descArray = json_decode($log->description, true) ?? ["Errors" => ''];
		$previousSuccessCount = $descArray["Success Count"] ?? 0;
		$previousFailedCount = $descArray["Failed Count"] ?? 0;

		$errorArray = [];
		$success = 0;
		$failed = 0;

		foreach ($this->chunk as $row) {
			$rowData = [];
			$rowError = [];

			/* Validate column count */
			if (count($this->header) === count($row)) {
				$rowData = array_combine($this->header, $row);
			} else {
				$rowError[] = 'Column mismatch: Row has incorrect data structure.';
				$this->logError($rowError, $failed, $success, $previousSuccessCount, $previousFailedCount, $errorArray);
				$failed++;
				continue;
			}

			foreach ($this->fileFormatArray as $headerKey => $variableName) {
				if (in_array($headerKey, $this->header)) {
					${$variableName} = trim($rowData[$headerKey]);
				}
			}
			if (empty($measurementType)) {
				$rowError[] = "'Measurement Type' is missing";
			}
			if (empty($productFamily)) {
				$rowError[] = "'Product Family' is missing";
			}
			if (empty($primaryUnit)) {
				$rowError[] = "'Primary Unit' is missing";
			}

			if (!empty($rowError)) {
				$this->logError($rowError, $failed, $success, $previousSuccessCount, $previousFailedCount, $errorArray);
				$failed++;
				continue;
			}
			/* Measurement Type validation */
			if (!in_array($measurementType, array_values($measurementTypeIdNames))) {
				$rowError[] = "$measurementType measurement type does not exist.";
			} else {
				$measurementTypeID = array_search($measurementType, $measurementTypeIdNames);
			}

			/* Product Family Type validation */
			if (!in_array($productFamily, array_values($productFamilyIdNames))) {
				$rowError[] = "$productFamily product family does not exist.";
			} else {
				$productFamilyID = array_search($productFamily, $productFamilyIdNames);
			}

			if (!empty($rowError)) {
				$this->logError($rowError, $failed, $success, $previousSuccessCount, $previousFailedCount, $errorArray);
				$failed++;
				continue;
			}

			/* Primary Unit validation */
			$primaryUnitID = $measurementTypeIdArray[$measurementTypeID][$primaryUnit] ?? null;
			if (!$primaryUnitID) {
				$rowError[] = "$primaryUnit primary unit does not exist.";
			}

			/* Secondary Unit validation */
			if (!empty($secondaryUnit)) {
				$secondaryUnitID = $measurementTypeIdArray[$measurementTypeID][$secondaryUnit] ?? null;
				if (!$secondaryUnitID) {
					$rowError[] = "$secondaryUnit secondary unit does not exist.";
				}
			} else {
				$secondaryUnitID = null;
			}

			if (!empty($rowError)) {
				$this->logError($rowError, $failed, $success, $previousSuccessCount, $previousFailedCount, $errorArray);
				$failed++;
				continue;
			}

			/* Start Transaction */
			DB::beginTransaction();

			try {
				$checkExist = CategoryMeasurementUnitPriority::where('measurement_type_id', $measurementTypeID)->where('category_id', $productFamilyID)->first();
				if ($checkExist) {
					$checkExist->update([
						'measurement_unit_primary_id' => $primaryUnitID,
						'measurement_unit_secondary_id' => $secondaryUnitID,
						'updated_by' => $this->userId,
					]);
				} else {
					CategoryMeasurementUnitPriority::create([
						'measurement_type_id' => $measurementTypeID,
						'category_id' => $productFamilyID,
						'measurement_unit_primary_id' => $primaryUnitID,
						'measurement_unit_secondary_id' => $secondaryUnitID,
						'created_by' => $this->userId,
					]);
				}

				DB::commit();

				$success++;
			} catch (Throwable $e) {
				DB::rollBack();

				$rowError = [
					'Error processing row: ' . $e->getMessage(),
					'File: ' . $e->getFile(),
					'Line: ' . $e->getLine()
				];

				$this->logError($rowError, $failed, $success, $previousSuccessCount, $previousFailedCount, $errorArray);
				$failed++;
			}
		}

		/* Update Transaction Log */
		$this->updateTransactionLog($log, $success, $failed, $errorArray);
	}

	/**
	 * Log errors for a specific row.
	 */
	private function logError(&$rowError, $failed, $success, $previousSuccessCount, $previousFailedCount, &$errorArray)
	{
		$errorArray[] = [
			"Row Number" => $failed + $success + 2 + $previousSuccessCount + $previousFailedCount,
			"Error" => implode(' | ', $rowError),
		];
	}

	/**
	 * Update Transaction Log.
	 */
	private function updateTransactionLog($log, $success, $failed, $errorArray)
	{
		$descArray = json_decode($log->description, true) ?? ["Errors" => ''];
		$descArray["Success Count"] = ($descArray["Success Count"] ?? 0) + $success;
		$descArray["Failed Count"] = ($descArray["Failed Count"] ?? 0) + $failed;
		$descArray["Errors"] = array_merge($descArray["Errors"], $errorArray);

		$log->update([
			'description' => json_encode($descArray),
		]);
	}

	/**
	 * Handle a job failure.
	 */
	public function failed(\Throwable $exception): void
	{
		$log = TransactionLog::where('identifier', $this->batch()->id)->first();

		if (!$log) {
			logger()->error('Transaction log not found for batch: ' . $this->batch()->id);
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

		logger()->error("{$jobName} failed", $errorDetails);

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
