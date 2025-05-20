<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

use App\Models\Product;
use App\Models\Vendor;
use App\Models\ProductSupplier;
use App\Models\TransactionLog;

class ImportProductSupplierJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

	public $timeout = 43200;

	protected $header;
	protected $chunk;
	protected $userId;
	protected $fileFormatArray;

	/**
	 * Create a new job instance.
	 *
	 * @param array $data
	 */
	public function __construct(array $data)
	{
		$this->fileFormatArray = $data['fileFormatArray'];
		$this->header = $data['header'];
		$this->chunk = $data['chunk'];
		$this->userId = $data['userId'];
	}

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
	public function handle()
	{
		$log = TransactionLog::where('identifier', $this->batch()->id)->first();
		$descArray = json_decode($log->description, true) ?? ["Errors" => ''];
		$previousSuccessCount = $descArray["Success Count"] ?? 0;
		$previousFailedCount = $descArray["Failed Count"] ?? 0;

		$deliveryTimeOptions = [
			'2 to 3 Days',
			'5 to 7 Days',
			'10 to 12 Days',
			'3 to 4 Weeks',
			'6 Weeks',
			'8 to 10 Weeks',
			'12 Weeks'
		];


		$warrantyOptions = [
			'1 Month',
			'2 Months',
			'3 Months',
			'6 Months',
			'1 Year',
			'2 Years',
			'3 Years',
			'5 Years',
			'10 Years',
			'Lifetime Warranty'
		];

		$refundPeriods = [
			'7 Days', '14 Days', '30 Days', '60 Days', '90 Days'
		];

		$errorArray = [];
		$success = 0;
		$failed = 0;

		foreach ($this->chunk as $index => $row) {
			$rowData = [];
			$rowErrors = [];

			/* Validate column count */
			if (count($this->header) === count($row)) {
				$rowData = array_combine($this->header, $row);
			} else {
				$rowErrors[] = "The data in this row is not compatible for import.";
				$rowErrors[] = "Column count: ".count($this->header);
				$rowErrors[] = "Row data count: ".count($row);
				$this->logError($rowErrors, $failed, $success, $previousSuccessCount, $previousFailedCount, $errorArray);
				$failed++;
				continue;
			}

			/* Map data fields using field mapping */
			foreach ($this->fileFormatArray as $header => $varName) {
				${$varName} = trim($rowData[$header] ?? '');
			}

			/* Basic validation */
			if (empty($sku)) {
				$rowErrors[] = 'SKU is required.';
			}

			if (empty($vendor_name)) {
				$rowErrors[] = 'Vendor name is required.';
			}

			if (empty($vendor_sku)) {
				$rowErrors[] = 'Vendor SKU is required.';
			}

			if (!empty($rowErrors)) {
				$this->logError($rowErrors, $failed, $success, $previousSuccessCount, $previousFailedCount, $errorArray);
				$failed++;
				continue;
			}

			/* Product existence check */
			$product = Product::where('sku', $sku)->first();
			if (!$product) {
				$rowErrors[] = "No product found with SKU: {$sku}.";
			} else {
				$productID = $product->id;
			}

			/* Vendor existence check */
			$vendor = Vendor::where('name', $vendor_name)->first();
			if (!$vendor) {
				$rowErrors[] = "No vendor found with name: {$vendor_name}.";
			} else {
				$vendorID = $vendor->id;
			}

			/* Validate price logic */
			if (!empty($price) && !empty($sale_price) && (float)$price < (float)$sale_price)
			{
				$rowErrors[] = 'Price cannot be less than sale price.';
			}

			/* Existing supplier check */
			if (!empty($id)) {
				$existingSupplier = ProductSupplier::find($id);
				if (!$existingSupplier) {
					$rowErrors[] = "Supplier with ID {$id} not found.";
				}
			} else {
				$existingSupplier = ProductSupplier::where('product_id', $productID)->where('vendor_id', $vendorID)->first();
			}

			if (!in_array($delivery_days, $deliveryTimeOptions)) {
				$rowErrors[] = "Invalid Delivery Days: '$delivery_days'.";
			}

			if (!in_array($warranty_information, $warrantyOptions)) {
				$rowErrors[] = "Invalid Warranty Information: '$warranty_information'.";
			}

			if (!in_array($refund, $refundPeriods)) {
				$rowErrors[] = "Invalid Refund Period: '$refund'.";
			}

			/* If errors exist, log and continue to next row */
			if (!empty($rowErrors)) {
				$this->logError($rowErrors, $failed, $success, $previousSuccessCount, $previousFailedCount, $errorArray);
				$failed++;
				continue;
			}

			/* Cast to float */
			$cost_per_item = (float) $cost_per_item;
			$additional_cost = (float) $additional_cost;
			$price = (float) $price;
			$sale_price = (float) $sale_price;

			$final_cost_price = $cost_per_item + $additional_cost;

			if ($sale_price > 0) {
				$margin = (($sale_price - $final_cost_price) / $sale_price) * 100;
			} elseif ($price > 0) {
				$margin = (($price - $final_cost_price) / $price) * 100;
			} else {
				$margin = null;
			}

			/* Start Transaction */
			DB::beginTransaction();

			try {

				if (!$existingSupplier) {
					$supplier = new ProductSupplier();
					$supplier->created_by = $this->userId;
					$supplier->created_at = now();
				} else {
					$supplier = $existingSupplier;
					$supplier->updated_by = $this->userId;
					$supplier->updated_at = now();
				}

				/* Set required fields */
				$supplier->product_id = $productID;
				$supplier->vendor_id = $vendorID;
				$supplier->vendor_sku = $vendor_sku;

				$supplier->cost_per_item = $cost_per_item;
				$supplier->additional_cost = $additional_cost;
				$supplier->price = $price;
				$supplier->sale_price = $sale_price;
				$supplier->inventory = $inventory;
				$supplier->in_stock = strtolower($in_stock) === 'yes' ? 1 : 0;

				$supplier->delivery_days = $delivery_days;
				$supplier->warranty_information = $warranty_information;
				$supplier->refund = $refund;
				$supplier->final_cost_price = $final_cost_price;
				$supplier->margin = $margin;
				$supplier->save();

				DB::commit();
				$success++;
			} catch (Throwable $e) {
				DB::rollBack();
				$rowErrors = [
					'Error processing row: ' . $e->getMessage(),
					'File: ' . $e->getFile(),
					'Line: ' . $e->getLine()
				];

				$this->logError($rowErrors, $failed, $success, $previousSuccessCount, $previousFailedCount, $errorArray);
				$failed++;
			}
		}

		/* Update Transaction Log */
		$this->updateTransactionLog($log, $success, $failed, $errorArray);
	}

	/**
	 * Log errors for a specific row.
	 *
	 * @param array $rowErrors
	 * @param int $failed
	 * @param int $success
	 * @param int $previousSuccessCount
	 * @param int $previousFailedCount
	 * @param array $errorArray
	 * @return void
	 */
	private function logError(&$rowErrors, $failed, $success, $previousSuccessCount, $previousFailedCount, &$errorArray)
	{
		$errorArray[] = [
			"Row Number" => $failed + $success + 2 + $previousSuccessCount + $previousFailedCount,
			"Error" => implode(' | ', $rowErrors),
		];
	}

	/**
	 * Update Transaction Log.
	 *
	 * @param TransactionLog $log
	 * @param int $success
	 * @param int $failed
	 * @param array $errorArray
	 * @return void
	 */
	private function updateTransactionLog($log, $success, $failed, $errorArray)
	{
		$descArray = json_decode($log->description, true) ?? ["Errors" => ''];
		$descArray["Success Count"] = ($descArray["Success Count"] ?? 0) + $success;
		$descArray["Failed Count"] = ($descArray["Failed Count"] ?? 0) + $failed;
		$descArray["Errors"] = array_merge($descArray["Errors"] ?? [], $errorArray);

		$log->update([
			'description' => json_encode($descArray),
		]);
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
		Log::error("Supplier Import Error: " . $error);
	}
}