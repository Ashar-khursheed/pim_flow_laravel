<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Models\ProductSupplier;
use App\Models\TransactionLog;

class ImportSupplierJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public $timeout = 43200;

    protected $header;
    protected $chunk;
    protected $userId;
    protected $fieldMapping;

    /**
     * Create a new job instance.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->header = $data['header'];
        $this->chunk = $data['chunk'];
        $this->userId = $data['userId'];
        $this->fieldMapping = $data['fileFormatArray'];
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

        $errorArray = [];
        $success = 0;
        $failed = 0;

        foreach ($this->chunk as $index => $row) {
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

            /* Map data fields using field mapping */
            $mappedData = [];
            foreach ($this->fieldMapping as $header => $fieldName) {
                $mappedData[$fieldName] = trim($rowData[$header] ?? '');
            }

            /* Basic validation */
            if (empty($mappedData['sku'])) {
                $rowError[] = 'SKU is required.';
            }
            
            if (empty($mappedData['vendor_id'])) {
                $rowError[] = 'Vendor ID is required.';
            }
            
            if (empty($mappedData['vendor_sku'])) {
                $rowError[] = 'Vendor SKU is required.';
            }

            /* Product existence check */
            $product = null;
            if (!empty($mappedData['sku'])) {
                $product = DB::table('ec_products')->where('sku', $mappedData['sku'])->first();
                if (!$product) {
                    $rowError[] = "No product found with SKU: {$mappedData['sku']}.";
                }
            }

            /* Vendor existence check */
            if (!empty($mappedData['vendor_id'])) {
                $vendor = DB::table('vendors')->where('id', $mappedData['vendor_id'])->first();
                if (!$vendor) {
                    $rowError[] = "Vendor with ID {$mappedData['vendor_id']} not found.";
                }
            }

            /* Validate price logic */
            if (!empty($mappedData['price']) && !empty($mappedData['sale_price']) && 
                (float)$mappedData['price'] < (float)$mappedData['sale_price']) {
                $rowError[] = 'Price cannot be less than sale price.';
            }

            /* Existing supplier check */
            $existingSupplier = null;
            if (!empty($mappedData['id'])) {
                $existingSupplier = ProductSupplier::find($mappedData['id']);
                if (!$existingSupplier) {
                    $rowError[] = "Supplier with ID {$mappedData['id']} not found.";
                }
            } elseif (!empty($mappedData['sku']) && !empty($mappedData['vendor_id'])) {
                $product_id = $product ? $product->id : null;
                if ($product_id) {
                    $existingSupplier = ProductSupplier::where('product_id', $product_id)
                        ->where('vendor_id', $mappedData['vendor_id'])
                        ->first();
                }
            }

            /* Start Transaction */
            DB::beginTransaction();

            try {
                /* If errors exist, log and continue to next row */
                if (!empty($rowError)) {
                    $this->logError($rowError, $failed, $success, $previousSuccessCount, $previousFailedCount, $errorArray);
                    $failed++;
                    DB::rollBack();
                    continue;
                }

                if (!$existingSupplier) {
                    $existingSupplier = new ProductSupplier();
                    $existingSupplier->created_at = now();
                }

                $product_id = $product ? $product->id : null;

                /* Set required fields */
                $existingSupplier->sku = $mappedData['sku'];
                $existingSupplier->vendor_sku = $mappedData['vendor_sku'];
                $existingSupplier->vendor_id = $mappedData['vendor_id'];
                $existingSupplier->product_id = $product_id;

                /* Set optional fields */
                $existingSupplier->warranty_information = $mappedData['warranty_information'] ?? null;
                $existingSupplier->refund = $mappedData['refund'] ?? null;
                $existingSupplier->delivery_days = $mappedData['delivery_days'] ?? null;
                
                /* Set numeric fields with type casting */
                if (isset($mappedData['cost_per_item'])) {
                    $existingSupplier->cost_per_item = (float)$mappedData['cost_per_item'];
                }
                
                if (isset($mappedData['sale_price'])) {
                    $existingSupplier->sale_price = (float)$mappedData['sale_price'];
                }
                
                if (isset($mappedData['price'])) {
                    $existingSupplier->price = (float)$mappedData['price'];
                }
                
                if (isset($mappedData['margin'])) {
                    $existingSupplier->margin = (float)$mappedData['margin'];
                }
                
                if (isset($mappedData['inventory'])) {
                    $existingSupplier->inventory = (int)$mappedData['inventory'];
                }
                
                if (isset($mappedData['additional_cost'])) {
                    $existingSupplier->additional_cost = (float)$mappedData['additional_cost'];
                }
                
                if (isset($mappedData['final_cost_price'])) {
                    $existingSupplier->final_cost_price = (float)$mappedData['final_cost_price'];
                }

                $existingSupplier->updated_at = now();
                $existingSupplier->save();

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
     *
     * @param array $rowError
     * @param int $failed
     * @param int $success
     * @param int $previousSuccessCount
     * @param int $previousFailedCount
     * @param array $errorArray
     * @return void
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