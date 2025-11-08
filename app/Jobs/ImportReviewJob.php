<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\DB;
use App\Models\TransactionLog;
use Illuminate\Support\Facades\Log;
use App\Models\Language;
use App\Models\Review;



class ImportReviewJob implements ShouldQueue
{

    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $fileFormatArray;
    protected $header;
    protected $rows;
    protected $userId;
    protected $fieldMapping;
    /**
     * Create a new job instance.
     */
    public function __construct($data)
    {  
        $this->header = $data['header'];
        $this->rows = $data['chunk'];
        $this->userId = $data['userId'];
        $this->fieldMapping = $data['fileFormatArray'];
        $this->userRole = $data['userRole'];
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $existingProductIds = Review::pluck('product_id')->toArray();
        $log = TransactionLog::where('identifier', $this->batch()->id)->first();
        $desc = json_decode($log->description, true) ?? ["Errors" => [], "Success Count" => 0, "Failed Count" => 0];
        $prevSuccess = $desc["Success Count"] ?? 0;
        $prevFail = $desc["Failed Count"] ?? 0;

        $errors = [];
        $successCount = 0;
        $failCount = 0;
        $updateCount = 0; // Track updates separately

        foreach ($this->rows as $index => $row) {
            $rowErrors = [];

            // Validate column count
            if (count($this->header) !== count($row)) {
                $rowErrors[] = 'Invalid column count.';
                $this->logError($errors, $index, $rowErrors);
                $failCount++;
                continue;
            }

            // Combine header with row data
            $data = array_combine($this->header, $row);
 
            // Extract variables based on field mapping
            $mappedData = [];
            foreach ($this->fieldMapping as $header => $varName) {
                $mappedData[$varName] = trim($data[$header] ?? '');
            }

            // Extract specific fields
            $productId = $mappedData['product_id'] ?? '';         
            $review1 = $mappedData['Review1'] ?? '';
            $review2 = $mappedData['Review2'] ?? '';
            $review3 = $mappedData['Review3'] ?? '';
            $review4 = $mappedData['Review4'] ?? '';
            $review5 = $mappedData['Review5'] ?? '';

            // Validate required fields
            if (empty($productId)) {
                $rowErrors[] = 'Product ID is required.';
            }
       

            if (!empty($rowErrors)) {
                $this->logError($errors, $index, $rowErrors);
                $failCount++;
                continue;
            }

            $reviews = [
                $review1,
                $review2,
                $review3,
                $review4,
                $review5,
            ];

            /* Save or Update review */
            try {
                DB::beginTransaction();

                $savedCount = 0;
                $updatedCount = 0;

                foreach ($reviews as $reviewComment) {
                    // Skip empty reviews
                    if (empty(trim($reviewComment))) {
                        continue;
                    }

                    // Get random customer for each review
                    $randomCustomer = \DB::table('customers')->inRandomOrder()->first();
 
                    if (!$randomCustomer) {
                        continue;
                    }

                    // Check if review already exists with this product_id and customer_id
                    $existingReview = Review::where('product_id', $productId)
                        ->where('customer_id', $randomCustomer->id)
                        ->first();

                    if ($existingReview) {
                        // UPDATE existing review
                        $existingReview->comment = $reviewComment;
                        $existingReview->status = "published";
                        $existingReview->star = rand(3, 5);
                        $existingReview->updated_at = now();                 
                        $existingReview->save();

                        $updatedCount++;
                        Log::info("Updated review ID {$existingReview->id} for product {$productId} and customer {$randomCustomer->id}");
                    } else {
                        // CREATE new review
                        $review = new Review();
                        $review->product_id = $productId;
                        $review->comment = $reviewComment;
                        $review->status = "published";
                        $review->star = rand(3, 5);
                        $review->customer_id = $randomCustomer->id;
                        $review->customer_name = $randomCustomer->name;
                        $review->customer_email = $randomCustomer->email;
                        $review->created_at = now();                     
                        $review->updated_at = now();                      
                        $review->images = null;
                        $review->save();

                        $savedCount++;
                        Log::info("Created new review ID {$review->id} for product {$productId} and customer {$randomCustomer->id}");
                    }
                }

                DB::commit();

                if ($savedCount > 0 || $updatedCount > 0) {
                    $successCount++;
                    $updateCount += $updatedCount;
                    Log::info("Product {$productId}: Created {$savedCount} reviews, Updated {$updatedCount} reviews");
                } else {
                    $failCount++;
                    $this->logError($errors, $index, ['No valid reviews found for this product']);
                }

            } catch (\Exception $e) {
                DB::rollBack();

                Log::error('Review Import Error', [
                    'row' => $index + 2,
                    'product_id' => $productId,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                $this->logError($errors, $index, [
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
        $desc["Update Count"] = ($desc["Update Count"] ?? 0) + $updateCount; // Track updates
        $desc["Errors"] = array_merge($desc["Errors"] ?? [], $errors);

        $log->update(['description' => json_encode($desc)]);
    }

    /**
     * Log error with row details
     */
    private function logError(array &$errors, int $index, array $rowErrors)
    {
        $rowNumber = $index + 2; // +2 because index starts at 0 and first row is header
        $errors[] = [
            'row' => $rowNumber,
            'errors' => $rowErrors
        ];
    }


}
