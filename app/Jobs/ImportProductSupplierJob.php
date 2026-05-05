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
use App\Models\ProductPriceTracking;
use App\Models\TransactionLog;

class ImportProductSupplierJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

	protected $header;
	protected $chunk;
	protected $userId;
	protected $fileFormatArray;
	protected $userRole;

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
		$this->userRole = $data['userRole'];
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

		Log::info('Product Supplier Job:', ['previousSuccessCount' => $previousSuccessCount, 'previousFailedCount' => $previousFailedCount]);

		$deliveryTimeOptions = app_constants('DELIVERY_DAYS');
		$warrantyOptions = app_constants('WARRANTY_OPTIONS');
		$returnPolicies = app_constants('RETURN_POLICY');
		$inStockOptions = app_constants('IN_STOCK_OPTIONS');
		$isFixedOptions = app_constants('IS_FIXED_OPTIONS');
		$freeShippingOptions = app_constants('FREE_SHIPPING_OPTIONS');

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
			if (empty($product_id) && empty($product_name)) {
				$rowErrors[] = "Either 'Product ID' or 'Product Name' is required.";
			} else {
				$product = null;

				if (!empty($product_id)) {
					$product = Product::find($product_id);
				} elseif (!empty($product_name)) {
					$product = Product::where('name', $product_name)->first();
				}

				if (!$product) {
					if (!empty($product_id)) {
						$rowErrors[] = "'Product ID' is not valid.";
					} elseif (!empty($product_name)) {
						$rowErrors[] = "'Product Name' is not valid.";
					}
				} else {
					$productID = $product->id;
				}
			}

			if (!in_array($this->userRole, ['Admin', 'Super Admin']) && $product->approved == 1) {
				$rowErrors[] = "This product has already been approved and cannot be modified.";
				$this->logError($rowErrors, $failed, $success, $previousSuccessCount, $previousFailedCount, $errorArray);
				$failed++;
				continue;
			}

			if (empty($vendor_id) && empty($vendor_name)) {
				$rowErrors[] = "Either 'Vendor ID' or 'Vendor Name' is required.";
			} else {
				$vendor = null;

				if (!empty($vendor_id)) {
					$vendor = Vendor::find($vendor_id);
				} elseif (!empty($vendor_name)) {
					$vendor = Vendor::where('name', $vendor_name)->first();
				}

				if (!$vendor) {
					if (!empty($vendor_id)) {
						$rowErrors[] = "'Vendor ID' is not valid.";
					} elseif (!empty($vendor_name)) {
						$rowErrors[] = "'Vendor Name' is not valid.";
					}
				} else {
					$vendorID = $vendor->id;
				}
			}

			/* cost_per_item must be provided OR both list_price and multiple must be present */
			if (empty($cost_per_item) && (empty($list_price) || empty($multiple))) {
				$rowErrors[] = "Either 'Cost Per Item' or both 'List Price' and 'Multiple' are required.";
			}

			if (empty($price)) {
				$rowErrors[] = "'Price' is required.";
			}

			/* Inventory must be numeric — 0 is allowed */
			if (!is_numeric($inventory)) {
				$rowErrors[] = "'Inventory' must be a numeric value.";
			}

			if (empty($delivery_days)) {
				$rowErrors[] = "'Delivery Days' are required.";
			}

			if (empty($return_policy)) {
				$rowErrors[] = "'Return Policy' is required.";
			}

			if (!empty($rowErrors)) {
				$this->logError($rowErrors, $failed, $success, $previousSuccessCount, $previousFailedCount, $errorArray);
				$failed++;
				continue;
			}

			/* Validate price logic */
			if (!empty($multiple) && ($multiple <= 0 || $multiple >= 1)) {
				$rowErrors[] = "'Multiple' must be greater than 0 and less than 1.";
			}

			if (!empty($map) && !empty($sale_price)  && (float)$map > (float)$sale_price)
			{
				$rowErrors[] = 'Sale Price cannot be less than MAP.';
			}

			/* Validate price logic */
			if (!empty($sale_price) && (float)$price < (float)$sale_price)
			{
				$rowErrors[] = 'Price cannot be less than sale price.';
			}

			if (!empty($map) && (float)$price < (float)$map)
			{
				$rowErrors[] = 'Price cannot be less than MAP.';
			}

			if (!empty($min_quantity) && (int)$min_quantity < 1) {
				$rowErrors[] = 'The minimum quantity must be at least 1.';
			}

			if (!in_array($delivery_days, $deliveryTimeOptions)) {
				$rowErrors[] = "Invalid Delivery Days: '$delivery_days'.";
			}

			if (!in_array($return_policy, $returnPolicies)) {
				$rowErrors[] = "Invalid Return Policy: '$return_policy'.";
			}

			if (!empty($in_stock) && !in_array($in_stock, $inStockOptions)) {
				$rowErrors[] = "Invalid In Stock Option: '$in_stock'.";
			}

			if (!empty($is_fixed) && !in_array($is_fixed, $isFixedOptions)) {
				$rowErrors[] = "Invalid Is Fixed Option: '$is_fixed'.";
			}

			if (!empty($free_shipping) && !in_array($free_shipping, $freeShippingOptions)) {
				$rowErrors[] = "Invalid Free Shipping Option: '$free_shipping'.";
			}

			if (!empty($free_shipping) && $free_shipping === 'No') {
				if (empty($shipping_charge) || $shipping_charge == 0) {
					$rowErrors[] = "Shipping charge is required and must be greater than 0 when Free Shipping = No.";
				}
			}

			if (!empty($shipping_charge) && !is_numeric($shipping_charge)) {
				$rowErrors[] = "Shipping charge must be a numeric value.";
			}

			if (!empty($warranty_information) && !in_array($warranty_information, $warrantyOptions)) {
				$rowErrors[] = "Invalid Warranty Information: '$warranty_information'.";
			}

			/* If errors exist, log and continue to next row */
			if (!empty($rowErrors)) {
				$this->logError($rowErrors, $failed, $success, $previousSuccessCount, $previousFailedCount, $errorArray);
				$failed++;
				continue;
			}

			/* Check for existing supplier */
			if (!empty($id)) {
				$existingSupplier = ProductSupplier::find($id);
				if (!$existingSupplier) {
					$rowErrors[] = "Supplier with ID {$id} not found.";
				}
			} else {
				$existingSupplier = ProductSupplier::where('product_id', $productID)
				->where('vendor_id', $vendorID)
				->first();
			}

			/* If errors exist, log and continue to next row */
			if (!empty($rowErrors)) {
				$this->logError($rowErrors, $failed, $success, $previousSuccessCount, $previousFailedCount, $errorArray);
				$failed++;
				continue;
			}

			/* Cost calculations */
			$costPerItem = (!empty($list_price) && !empty($multiple))
			? (float)$list_price * (float)$multiple
			: (float)$cost_per_item;

			$additionalCost = !empty($additional_cost)
			? $costPerItem * ((float)$additional_cost / 100)
			: 0;

			$surchargeCost = !empty($surcharge)
			? $costPerItem * ((float)$surcharge / 100)
			: 0;

			$totalCostPerItem = $costPerItem + $additionalCost + $surchargeCost;

			/* Price and Margin */
			$price = !empty($price) ? (float)$price : 0;
			$salePrice = !empty($sale_price) ? (float)$sale_price : 0;

			if ($salePrice > 0) {
				$margin = (($salePrice - $totalCostPerItem) / $salePrice) * 100;
			} elseif ($price > 0) {
				$margin = (($price - $totalCostPerItem) / $price) * 100;
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

					/* New supplier — assign last priority */
					$supplier->priority = ProductSupplier::where('product_id', $productID)->count() + 1;
				} else {
					$supplier = $existingSupplier;
					$supplier->updated_by = $this->userId;
					$supplier->updated_at = now();
				}

				/* Set values */
				$supplier->product_id = $productID;
				$supplier->vendor_id = $vendorID;
				$supplier->vendor_sku = $vendor_sku;
				$supplier->list_price = $list_price !== null ? (float) $list_price : null;
				$supplier->multiple = $multiple !== null ? (float) $multiple : null;
				$supplier->cost_per_item = $costPerItem;
				$supplier->surcharge = $surcharge !== null ? (float) $surcharge : null;
				$supplier->additional_cost = $additional_cost !== null ? (float) $additional_cost : null;
				$supplier->total_cost_per_item = $totalCostPerItem;
				$supplier->map = $map !== null ? (float) $map : null;
				$supplier->sale_price = $salePrice;
				$supplier->price = $price;
				$supplier->inventory = !empty($inventory) ? (int) $inventory : null;
				$supplier->in_stock = ($inventory > 0) ? 1 : (!empty($in_stock) && strtolower($in_stock) === 'yes' ? 1 : 0);
				$supplier->min_quantity = !empty($min_quantity) ? (int) $min_quantity : 1;
				$supplier->is_fixed = (!empty($is_fixed) && strtolower($is_fixed) === 'yes') ? 1 : 0;
				$supplier->delivery_days = $delivery_days;
				$supplier->return_policy = $return_policy;
				$supplier->free_shipping = (!empty($free_shipping) && strtolower($free_shipping) === 'yes') ? 1 : 0;
				$supplier->shipping_charge = $supplier->free_shipping == 1 ? 0 : $shipping_charge;
				$supplier->margin = $margin;
				$supplier->restocking_fees = $restocking_fees !== null ? (float) $restocking_fees : null;
				$supplier->warranty_information = $warranty_information !== null ? (float) $warranty_information : null;
				$supplier->save();

				/* Track changes for price, sale_price and inventory — only if value changed */

				if ($existingSupplier) {
					/* Capture old values before update for tracking */
					$oldPrice = $existingSupplier->price ?? null;
					$oldSalePrice = $existingSupplier->sale_price ?? null;
					$oldInventory = $existingSupplier->inventory ?? null;
					$trackingData = [];

					if ((float) $oldPrice !== (float) $price) {
						$trackingData[] = [
							'product_price_id' => $supplier->id,
							'field' => 'price',
							'old_value' => $oldPrice,
							'new_value' => $price,
							'created_by' => $this->userId,
						];
					}

					if ((float) $oldSalePrice !== (float) $salePrice) {
						$trackingData[] = [
							'product_price_id' => $supplier->id,
							'field' => 'sale_price',
							'old_value' => $oldSalePrice,
							'new_value' => $salePrice,
							'created_by' => $this->userId,
						];
					}

					if ((int) $oldInventory !== (int) $inventory) {
						$trackingData[] = [
							'product_price_id' => $supplier->id,
							'field' => 'inventory',
							'old_value' => $oldInventory,
							'new_value' => $inventory,
							'created_by' => $this->userId,
						];
					}

					if (!empty($trackingData)) {
						ProductPriceTracking::insert($trackingData);
					}
				}

				DB::commit();
				$success++;
			} catch (Throwable $e) {
				DB::rollBack();
				$rowErrors = [
					'Error processing row: ' . $e->getMessage(),
					'File: ' . $e->getFile(),
					'Line: ' . $e->getLine(),
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