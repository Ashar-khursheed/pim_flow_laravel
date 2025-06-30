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

	public $timeout = 43200;

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
	 *
	 * @param Throwable $exception
	 * @return void
	 */
	public function failed(Throwable $exception): void
	{
		$error = $exception->getMessage() . "\n" . $exception->getTraceAsString();
		Log::error("Product Title update Error: " . $error);
	}

}