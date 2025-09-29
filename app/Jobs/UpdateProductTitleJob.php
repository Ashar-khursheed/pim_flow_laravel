<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;

use Illuminate\Support\Facades\Log;
use Throwable;

use App\Models\Attribute;
use App\Models\TransactionLog;
use App\Models\Product;

class UpdateProductTitleJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

	protected $products;
	protected $formulaAttributes;
	protected $formulaAttributeIds;
	protected $userId;

	/**
	 * Create a new job instance.
	 *
	 * @param array $data
	 */
	public function __construct(array $data)
	{
		$this->products = $data['products'];
		$this->formulaAttributes = $data['formulaAttributes'];
		$this->formulaAttributeIds = $data['formulaAttributeIds'];
		$this->userId = $data['userId'];
	}

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
	public function handle()
	{
		$errorArray = [];
		$success = 0;
		$failed = 0;

		foreach ($this->products as $product) {
			$attributeValues = $product->productAttributes()
				->whereIn('attribute_id', $this->formulaAttributeIds)
				->get()
				->keyBy('attribute_id');

			$missingIds = collect($this->formulaAttributeIds)
				->filter(fn($id) => !$attributeValues->has($id))
				->values()
				->toArray();

			if (empty($missingIds)) {
				$generatedTitle = $this->formulaAttributes
					->map(fn($attr) => $attributeValues[$attr->id]->attribute_value)
					->implode(' ');

				Product::$observerUserId = $this->userId;
				$product->update(['name' => $generatedTitle]);
				Product::$observerUserId = null;

				$success++;
			} else {
				$missingNames = Attribute::whereIn('id', $missingIds)->pluck('name')->toArray();
				$errorArray[] = [
					"Product ID" => $product->id,
					"Actual Product Name" => $product->name,
					"Error" => "Missing Attributes: " . implode(', ', $missingNames),
				];
				$failed++;
			}
		}

		$log = TransactionLog::where('identifier', $this->batch()->id)->first();
		if ($log) {
			$descArray = json_decode($log->description, true) ?? ["Errors" => []];
			$descArray["Success Count"] += $success;
			$descArray["Failed Count"] += $failed;
			$descArray["Errors"] = array_merge($descArray["Errors"], $errorArray);

			$log->update([
				'description' => json_encode($descArray, JSON_UNESCAPED_UNICODE),
			]);
		}
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