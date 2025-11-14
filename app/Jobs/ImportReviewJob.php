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
use App\Models\FrontEnd\Customer;
use Carbon\Carbon;
use Faker\Factory as Faker;


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
                    $faker = Faker::create();
                    $customer = [
                        'id'    => $faker->numberBetween(1, 999999), 
                        'name'  => $faker->name,
                        'email' => $faker->unique()->safeEmail,
                        'phone' => $faker->phoneNumber,
                    ];
                $notin = [
                    'webdeveloper01@horecastore.ae',
                    'webdeveloper02@horecastore.ae',
                    'webdeveloper03@horecastore.ae',
                    'webdeveloper04@horecastore.ae',
                    'webdeveloper05@horecastore.ae',
                    'webdeveloper06@horecastore.ae',
                    'webdeveloper07@horecastore.ae',
                    'webdeveloper08@horecastore.ae',
                    'marketing@rapidhotelsupplies.com',
                    'demo@gmail.com',
                    'qa01@mailinator',
                    'abcd@horecastore.ae',
                    've@horecastore.ae',
                    'es05@horecastore.ae',
                    'qa05@horecastore.ae',
                    'erpsupport@horecastore.ae',
                    'qa04@horecastore.ae',
                    'qa0445656@horecastore.ae',
                    'abcd@horecastore.ae',
                    'qa07@horecastore.ae',                    
                    'test786543@gmail.com',                    
                    'testbususer.usa.test@sharklasers.com',                    
                    'test52@mailinator.com',                    
                    'ndhake899@mailsac.com',                    
                    'test@midsummer.agency',                    
                    'testpvtuser.usa.test@sharklasers.com',                    
                    'test6788@gmail.com',                    
                    'testdev@mailinator.com',                    
                    'shezadrazzaq@gmail.com',                    
                    'dmm@thehorecastore.com',                    
                    'sussexmobil1@gmail.com',                    
                    'stevemcd1977@gmail.com',                    
                    'fserrapumba@gmail.com',                                      
                    'testdev01@mailinator.com',                    
                    'testing54@gmail.com',                    
                    'thesweetestlilthings1@gmail.com',                    
                    'testgaurav022@mailinator.com',                    
                    'test23@gmail.com',                    
                    'test@midsummer.agency',                    
                    '56test@mailinator.com',                    
                    'ndhake899@mailsac.com',                                                         
                    'shezadrazzaq@gmail.com',                    
                    'marouscha.dorenbos@midsummer.agency',                    
                    'marouscha.dorenbos@midsummer.agency',                    
                    'emmy.abdulghaffarllc@gmail.com',                    
                    'test786543@gmail.com',                    
                    'hassan.quantum647@hotmail.com',                    
                    'test1230@gmail.com',                    
                    'webdeveloper01@rapid-supplies.com',                    
                    'test.jasper@shopify.com',                    
                    'testmail@gmail.com',                    
                    'test43567@gmail.com',                    
                    'qa01@mailinator.com',                    
                    'dmm@thehorecastore.com',                    
                    'test786543@gmail.com',                    
                    'nikhiltest@gmail.com',                    
                    'qa01@mailinator.com',                    
                    'test@testkkk.com',                    
                    'jack@yopmail.com',                    
                    'jixaci8513@bawsny.com',                    
                    'testdev@mailinator.com',                    
                    'testsitelink@gmail.com',                    
                    'test6788@gmail.com',                    
                    'a1@mailinator.com',                    
                    '56test@mailinator.com',                    
                    'es05@horecastore.ae',                    
                    'jixaci8513@bawsny.com',                    
                    'test@midsummer.agency',                    
                    'moghlhashan@gmail.com',                    
                    'test45789@gmail.com',                    
                    'yesy@test.com',                    
                    'marymelito@aol.com',                    
                    'testtest@gmail.com',                    
                    'devtest@gmail.com',                    
                    'horecastore@mailinator.com',                    
                    'nailamemon1122@gmail.com',                    
                    'testshaki@gmail.com',                    
                    'test@example.com',                    
                    'testdev03@mailinator.com',                    
                    'whitestephen@example.com',                    
                    'hhartman@example.net',                    
                    'gfrancis@example.org',                    
                    ];
                    // Get random customer NOT in these emails
                    $randomCustomer = Customer::whereNotIn('email', $notin)
                    ->inRandomOrder()
                    ->first();

 
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
                        $existingReview->star = rand(4, 5);
                        $existingReview->customer_name = $customer['name'];
                        $existingReview->customer_email = $customer['email'];
                        $existingReview->created_at = Carbon::now()->subDays(rand(60, 730)); 
                        $existingReview->updated_at = Carbon::now()->subDays(rand(60, 730));                 
                        $existingReview->save();

                        $updatedCount++;
                        Log::info("Updated review ID {$existingReview->id} for product {$productId} and customer {$randomCustomer->id}");
                    } else {
                        // CREATE new review
                        $review = new Review();
                        $review->product_id = $productId;
                        $review->comment = $reviewComment;
                        $review->status = "published";
                        $review->star = rand(4, 5);
                        $review->customer_id = $customer['id'];
                        $review->customer_name = $customer['name'];
                        $review->customer_email = $customer['email'];
                        $review->created_at = Carbon::now()->subDays(rand(60, 730));                     
                        $review->updated_at = Carbon::now()->subDays(rand(60, 730));                      
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
