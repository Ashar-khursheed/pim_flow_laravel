<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\DB;

use App\Models\ProductSupplier;
use App\Models\TransactionLog;

class ImportSupplierJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public $timeout = 43200;

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
            if (empty($sku)) {
                $rowErrors[] = 'SKU is required.';
            }

            if (empty($vendor_id)) {
                $rowErrors[] = 'Vendor ID is required.';
            }

            if (empty($vendor_sku)) {
                $rowErrors[] = 'Vendor SKU is required.';
            }

            /* Check if product exists */
            $product = null;
            if (!empty($sku)) {
                $product = DB::table('ec_products')->where('sku', $sku)->first();
                if (!$product) {
                    $rowErrors[] = "No product found with SKU: $sku.";
                }
            }

            /* Check if vendor exists */
            if (!empty($vendor_id)) {
                $vendor = DB::table('vendors')->where('id', $vendor_id)->first();
                if (!$vendor) {
                    $rowErrors[] = "Vendor with ID $vendor_id not found.";
                }
            }

            /* Validate price logic if provided */
            if (
                !empty($price) && !empty($sale_price) &&
                (float)$price < (float)$sale_price
            ) {
                $rowErrors[] = 'Price cannot be less than sale price.';
            }

            /* Check if a record already exists */
            $existingSupplier = null;
            if (!empty($id)) {
                $existingSupplier = ProductSupplier::find($id);
                if (!$existingSupplier) {
                    $rowErrors[] = "Supplier with ID $id not found.";
                }
            } else if (!empty($sku) && !empty($vendor_id)) {
                // Check if this sku-vendor combination already exists
                $product_id = $product ? $product->id : null;
                if ($product_id) {
                    $existingSupplier = ProductSupplier::where('product_id', $product_id)
                        ->where('vendor_id', $vendor_id)
                        ->first();
                }
            }

            if (!empty($rowErrors)) {
                $this->logError($errors, $index, $prevSuccess, $prevFail, $rowErrors);
                $failCount++;
                continue;
            }

            /* Save supplier */
            try {
                DB::beginTransaction();

                if (!$existingSupplier) {
                    $existingSupplier = new ProductSupplier();
                    $existingSupplier->created_at = now();
                }

                $product_id = $product ? $product->id : null;

                $existingSupplier->sku = $sku;
                $existingSupplier->vendor_sku = $vendor_sku;
                $existingSupplier->vendor_id = $vendor_id;
                $existingSupplier->product_id = $product_id;
                
                if (isset($warranty_information)) {
                    $existingSupplier->warranty_information = $warranty_information;
                }
                
                if (isset($refund)) {
                    $existingSupplier->refund = $refund;
                }
                
                if (isset($delivery_days)) {
                    $existingSupplier->delivery_days = $delivery_days;
                }
                
                if (isset($cost_per_item)) {
                    $existingSupplier->cost_per_item = (float)$cost_per_item;
                }
                
                if (isset($sale_price)) {
                    $existingSupplier->sale_price = (float)$sale_price;
                }
                
                if (isset($price)) {
                    $existingSupplier->price = (float)$price;
                }
                
                if (isset($margin)) {
                    $existingSupplier->margin = (float)$margin;
                }
                
                if (isset($inventory)) {
                    $existingSupplier->inventory = (int)$inventory;
                }
                
                if (isset($additional_cost)) {
                    $existingSupplier->additional_cost = (float)$additional_cost;
                }
                
                if (isset($final_cost_price)) {
                    $existingSupplier->final_cost_price = (float)$final_cost_price;
                }

                $existingSupplier->updated_at = now();
                $existingSupplier->save();

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

    public function failed(\Throwable $exception): void
    {
        logger("Supplier Import Error: " . $exception->getMessage() . "\n" . $exception->getTraceAsString());
    }
}