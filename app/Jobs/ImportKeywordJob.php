<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\DB;

use App\Models\AppKeyword;
use App\Models\Language;
use App\Models\TransactionLog;

class ImportKeywordJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

	protected $header;
	protected $rows;
	protected $userId;
	protected $fieldMapping;

	public function __construct(array $data)
	{
		$this->header = $data['header'];
		$this->rows = $data['chunk'];
		$this->userId = $data['userId'];
		$this->fieldMapping = $data['fileFormatArray'];
	}

	public function handle()
	{
		$existingCodes = AppKeyword::pluck('code', 'id')->all();
		$langCodes = Language::pluck('code')->toArray();

		$log = TransactionLog::where('identifier', $this->batch()->id)->first();
		$desc = json_decode($log->description, true) ?? ["Errors" => ''];
		$prevSuccess = $desc["Success Count"] ?? 0;
		$prevFail = $desc["Failed Count"] ?? 0;

		$errors = [];
		$successCount = 0;
		$failCount = 0;

		foreach ($this->rows as $index => $row) {
			$rowErrors = [];

			if (count($this->header) !== count($row)) {
				$rowErrors[] = 'Invalid column count.';
				$this->logError($errors, $index, $prevSuccess, $prevFail, $rowErrors);
				$failCount++;
				continue;
			}

			$data = array_combine($this->header, $row);
			foreach ($this->fieldMapping as $header => $varName) {
				${$varName} = trim($data[$header] ?? '');
			}

			/* Validations */
			if (empty($code)) {
				$rowErrors[] = 'Code is required.';
			}

			$hasTranslation = false;
			foreach ($langCodes as $locale) {
				if (!empty(${$locale . '_title'})) {
					$hasTranslation = true;
					break;
				}
			}

			if (!$hasTranslation) {
				$rowErrors[] = 'At least one translation is required.';
			}

			/* Fetch or create keyword */
			$appKeyword = null;
			if (!empty($id)) {
				$appKeyword = AppKeyword::find($id);
				if (!$appKeyword) {
					$rowErrors[] = "Keyword with ID $id not found.";
				} elseif ($appKeyword->code !== $code && in_array($code, $existingCodes)) {
					$existingId = array_search($code, $existingCodes);
					$rowErrors[] = "Code $code already used by ID $existingId.";
				}
			} else {
				if (in_array($code, $existingCodes)) {
					$existingId = array_search($code, $existingCodes);
					$rowErrors[] = "Code $code already used by ID $existingId.";
				}
			}

			if (!empty($rowErrors)) {
				$this->logError($errors, $index, $prevSuccess, $prevFail, $rowErrors);
				$failCount++;
				continue;
			}

			/* Save keyword */
			try {
				DB::beginTransaction();

				if (!$appKeyword) {
					$appKeyword = new AppKeyword();
					$appKeyword->created_by = $this->userId;
					$appKeyword->created_at = now();
				}

				$appKeyword->code = $code;
				$appKeyword->updated_at = now();
				$appKeyword->updated_by = $this->userId;
				$appKeyword->save();

				/* Save translations */
				foreach ($langCodes as $locale) {
					$title = ${$locale . '_title'} ?? null;
					if (!empty($title)) {
						if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
							$appKeyword->translateOrNew($locale)->title = $title;
						}
					}
				}
				$appKeyword->save();

				$existingCodes[$appKeyword->id] = $code;

				DB::commit();
				$successCount++;
			} catch (\Exception $e) {
				DB::rollBack();
				$this->logError($errors, $index, $prevSuccess, $prevFail, [
					$e->getMessage(),
					"File: " . $e->getFile(),
					"Line: " . $e->getLine(),
				]);
				$failCount++;
			}
		}

		/* Update transaction log */
		$desc["Success Count"] = $prevSuccess + $successCount;
		$desc["Failed Count"] = $prevFail + $failCount;
		$desc["Errors"] = array_merge($desc["Errors"], $errors);

		$log->update(['description' => json_encode($desc)]);
	}

	protected function logError(&$errors, $index, $prevSuccess, $prevFail, array $rowErrors)
	{
		$errors[] = [
			"Row Number" => $index + 2 + $prevSuccess + $prevFail,
			"Error" => implode(' | ', $rowErrors),
		];
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
