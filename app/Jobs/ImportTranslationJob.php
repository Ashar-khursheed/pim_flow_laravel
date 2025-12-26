<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\DB;

use App\Models\Language;
use App\Models\TransactionLog;
use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\AttributeValue;
use App\Models\ProductAttribute;
use App\Models\Product;
use App\Models\FAQ;

class ImportTranslationJob implements ShouldQueue
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
		$langCodes = Language::pluck('code')->toArray();

		$log = TransactionLog::where('identifier', $this->batch()->id)->first();
		$desc = json_decode($log->description, true) ?? ["Errors" => ''];
		$prevSuccess = $desc["Success Count"] ?? 0;
		$prevFail = $desc["Failed Count"] ?? 0;
		$module = $log->module;

		$errors = [];
		$successCount = 0;
		$failCount = 0;

		/* Define type configuration */
		$typeConfig = [
			'Attribute Group Translation' => [
				'model' => AttributeGroup::class,
				'fields' => ['name'],
				'trans_fields' => ['name_tr'],
				'var_prefix' => 'name'
			],
			'Attribute Translation' => [
				'model' => Attribute::class,
				'fields' => ['name'],
				'trans_fields' => ['name_tr'],
				'var_prefix' => 'name'
			],
			'Attribute Value Translation' => [
				'model' => AttributeValue::class,
				'fields' => ['attribute_value'],
				'trans_fields' => ['attribute_value_tr'],
				'var_prefix' => 'attribute_value'
			],
			'Product Attribute Translation' => [
				'model' => ProductAttribute::class,
				'fields' => ['attribute_value'],
				'trans_fields' => ['attribute_value_tr'],
				'var_prefix' => 'attribute_value'
			],
			'Product Translation' => [
				'model' => Product::class,
				'fields' => ['name', 'description', 'benefits_features', 'images'],
				'trans_fields' => ['name_tr', 'description_tr', 'benefits_features_tr', 'images_tr'],
				'var_prefix' => null
			],
			'FAQ Translation' => [
				'model' => FAQ::class,
				'fields' => ['question', 'answer'],
				'trans_fields' => ['question_tr', 'answer_tr'],
				'var_prefix' => null
			],
		];

		$config = $typeConfig[$module] ?? null;

		if (!$config) {
			$this->logError($errors, 0, $prevSuccess, $prevFail, ["Invalid module type: {$module}"]);
			$this->updateLog($log, $desc, $errors, $prevSuccess, $prevFail, $successCount, $failCount);
			return;
		}

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
			if (empty($id)) {
				$rowErrors[] = 'ID is required.';
			}

			/* Check if at least one translation exists */
			$hasTranslation = false;
			foreach ($langCodes as $locale) {
				foreach ($config['trans_fields'] as $transField) {
					$baseField = str_replace('_tr', '', $transField);
					$varName = "{$locale}_{$baseField}";
					if (!empty(${$varName})) {
						$hasTranslation = true;
						break 2;
					}
				}
			}

			if (!$hasTranslation) {
				$rowErrors[] = 'At least one translation field is required.';
			}

			/* Fetch record */
			$model = $config['model'];
			$record = $model::find($id);
			if (!$record) {
				$rowErrors[] = "Record with ID {$id} not found.";
			}

			if (!empty($rowErrors)) {
				$this->logError($errors, $index, $prevSuccess, $prevFail, $rowErrors);
				$failCount++;
				continue;
			}

			/* Save translations */
			try {
				DB::beginTransaction();

				foreach ($langCodes as $locale) {
					$hasData = false;
					$translationData = [];

					/* Collect translation data */
					foreach ($config['trans_fields'] as $transField) {
						$baseField = str_replace('_tr', '', $transField);
						$varName = "{$locale}_{$baseField}";
						$value = ${$varName} ?? null;

						if (!empty($value)) {
							$hasData = true;
							$translationData[$transField] = $value;
						}
					}

					/* Save translation if has data */
					if ($hasData && in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
						$translation = $record->translations()
							->where('locale', $locale)
							->first();

						if (!$translation) {
							$translation = $record->translations()->create([
								'locale' => $locale,
								...$translationData
							]);
						} else {
							$translation->update($translationData);
						}
					}
				}

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
		$this->updateLog($log, $desc, $errors, $prevSuccess, $prevFail, $successCount, $failCount);
	}

	protected function updateLog($log, $desc, $errors, $prevSuccess, $prevFail, $successCount, $failCount)
	{
		$desc["Success Count"] = $prevSuccess + $successCount;
		$desc["Failed Count"] = $prevFail + $failCount;
		$desc["Errors"] = array_merge($desc["Errors"] ?? [], $errors);

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