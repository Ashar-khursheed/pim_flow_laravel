<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;

use Illuminate\Support\Facades\DB;
use Exception, Throwable;

use App\Models\RedirectLink;
use App\Models\TransactionLog;

class ImportRedirectLinkJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

	protected $header;
	protected $chunk;
	protected $fileFormatArray;

	public function __construct($data)
	{
		$this->header = $data['header'];
		$this->chunk = $data['chunk'];
		$this->fileFormatArray = $data['fileFormatArray'];

	}

	public function handle()
	{
		$log = TransactionLog::where('identifier', $this->batch()->id)->first();
		$descArray = json_decode($log->description, true) ?? ["Errors" => ''];
		$previousSuccessCount = $descArray["Success Count"] ?? 0;
		$previousFailedCount = $descArray["Failed Count"] ?? 0;

		$errorArray = [];
		$success = 0;
		$failed = 0;

		foreach ($this->chunk as $row) {
			$rowData = [];
			$rowError = [];
			if (count($this->header) == count($row)) {
				$rowData = array_combine($this->header, $row);
			} else {
				$rowError[] = 'The data in this row is not compatible for import.';
				$errorArray[] = [
					"Row Number" => $failed + $success + 2 + $previousSuccessCount + $previousFailedCount,
					"Error" => implode(' | ', $rowError)
				];
				$failed++;
				continue;
			}
			foreach ($this->fileFormatArray as $headerKey => $variableName) {
				if (in_array($headerKey, $this->header)) {
					${$variableName} = trim($rowData[$headerKey]);
				}
			}

			/* Required data validation */
			$rowError = [];

			foreach (['from' => $from, 'to' => $to] as $fieldName => $value) {
				if (empty($value)) {
					$rowError[] = "'".ucfirst($fieldName)."' is missing";
				} else {
					if (preg_match('/https?:\/\//i', $value)) {
						$rowError[] = "'".ucfirst($fieldName)."' should not contain http or https";
					}
					if (preg_match('/\bwww\./i', $value)) {
						$rowError[] = "'".ucfirst($fieldName)."' should not contain 'www.'";
					}
					if (preg_match('/[A-Z]/', $value)) {
						$rowError[] = "'".ucfirst($fieldName)."' should not contain capital letters";
					}
					if (preg_match('/\s/', $value)) {
						$rowError[] = "'".ucfirst($fieldName)."' should not contain spaces";
					}
				}
			}

			if (!empty($rowError)) {
				$errorArray[] = [
					"Row Number" => $failed + $success + 2 + $previousSuccessCount + $previousFailedCount,
					"Error" => implode(' | ', $rowError),
				];
				$failed++;
				continue;
			}

			DB::beginTransaction();

			try {
				/*************/
				$link = new RedirectLink();
				$link->from = $from;
				$link->to = $to;
				$link->save();

				DB::commit();
				$success++;
			} catch (\Exception $e) {
				DB::rollBack();

				$rowError[] = 'Error processing row: ' . $e->getMessage();
				$rowError[] = 'File: ' . $e->getFile();
				$rowError[] = 'Line: ' . $e->getLine();
				$errorArray[] = [
					"Row Number" => $failed + $success + 2 + $previousSuccessCount + $previousFailedCount,
					"Error" => implode(' | ', $rowError),
				];
				$failed++;
			}
		}

		/* Update Transaction Log */
		$log = TransactionLog::where('identifier', $this->batch()->id)->first();
		$descArray = json_decode($log->description, true) ?? ["Errors" => ''];
		$descArray["Success Count"] = $descArray["Success Count"] + $success;
		$descArray["Failed Count"] = $descArray["Failed Count"] + $failed;
		$descArray["Errors"] = array_merge($descArray["Errors"], $errorArray);

		TransactionLog::where('id', $log->id)->update([
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
