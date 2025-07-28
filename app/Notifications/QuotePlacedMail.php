<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class QuotePlacedMail extends Notification implements ShouldQueue
{
    use Queueable;

    public $timeout = 300; // 5 minutes (reduced from 12 hours)
    public $tries = 2; // Reduced attempts for faster debugging
    public $maxExceptions = 1;
    public $backoff = [30, 60]; // Shorter backoff

    public $quote;

    public function __construct($quote)
    {
        $this->quote = $quote;

        Log::info('QuotePlacedMail job created', [
            'quote_id' => $quote->id ?? 'unknown',
            'quote_number' => $quote->quote_number ?? 'unknown'
        ]);
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Determine if the notification should be sent.
     */
    public function shouldSend($notifiable, $channel)
    {
        $shouldSend = !empty($this->quote) && !empty($notifiable->email);

        Log::info('QuotePlacedMail shouldSend check', [
            'quote_id' => $this->quote->id ?? 'unknown',
            'notifiable_email' => $notifiable->email ?? 'empty',
            'should_send' => $shouldSend
        ]);

        return $shouldSend;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception)
    {
        Log::error('QuotePlacedMail notification failed permanently', [
            'quote_id' => $this->quote->id ?? 'unknown',
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'class' => get_class($exception)
        ]);
    }

    /**
     * Pre-download all product images before queuing the notification
     * Call this method BEFORE dispatching the notification
     */
    public static function preDownloadImages($quote)
    {
        try {
            Log::info('Starting pre-download of images for quote', [
                'quote_id' => $quote->id,
                'product_count' => $quote->quoteProducts->count()
            ]);

            $downloadedCount = 0;
            $failedCount = 0;

            foreach ($quote->quoteProducts as $index => $quoteProduct) {
                $productDetail = $quoteProduct->product;
                if (!$productDetail) {
                    Log::warning('Product detail not found', [
                        'quote_product_id' => $quoteProduct->id
                    ]);
                    continue;
                }

                Log::info('Processing product {$index )', [
                    'product_id' => $productDetail->id,
                    'product_name' => $productDetail->name
                ]);

                // Get product image URL
                $imageUrl = self::extractImageUrl($productDetail);
                if (!$imageUrl) {
                    Log::info('No image URL found for product', [
                        'product_id' => $productDetail->id
                    ]);
                    continue;
                }

                // Download and store the image
                $localPath = self::downloadProductImageSync($imageUrl, $productDetail->id, $quote->id);

                if ($localPath) {
                    $downloadedCount++;
                    Log::info('Successfully pre-downloaded image', [
                        'product_id' => $productDetail->id,
                        'local_path' => $localPath,
                        'url' => $imageUrl
                    ]);
                } else {
                    $failedCount++;
                    Log::warning('Failed to pre-download image', [
                        'product_id' => $productDetail->id,
                        'url' => $imageUrl
                    ]);
                }
            }

            Log::info('Pre-download completed', [
                'quote_id' => $quote->id,
                'downloaded' => $downloadedCount,
                'failed' => $failedCount,
                'total' => $quote->quoteProducts->count()
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Error in preDownloadImages', [
                'quote_id' => $quote->id,
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
            return false;
        }
    }

    /**
     * Extract image URL from product
     */
    private static function extractImageUrl($productDetail)
    {
        try {
            $images = is_array($productDetail->images)
                ? $productDetail->images
                : (is_array($decoded = json_decode($productDetail->images, true)) ? $decoded : null);

            $imageUrl = is_array($images) ? ($images[0] ?? null) : null;

            if (empty($imageUrl)) {
                return null;
            }

            // Handle relative URLs
            if (strpos($imageUrl, '//') === 0) {
                $imageUrl = 'https:' . $imageUrl;
            } elseif (strpos($imageUrl, '/') === 0) {
                $imageUrl = rtrim(config('app.url'), '/') . $imageUrl;
            }

            // Validate URL
            if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                Log::warning('Invalid image URL format', [
                    'product_id' => $productDetail->id,
                    'url' => $imageUrl
                ]);
                return null;
            }

            return $imageUrl;

        } catch (Exception $e) {
            Log::warning('Error extracting image URL', [
                'product_id' => $productDetail->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Download image synchronously and store locally
     */
    private static function downloadProductImageSync($imageUrl, $productId, $quoteId)
    {
        try {
            // Create directory structure
            $tempDir = "temp/quotes/{$quoteId}";
            if (!Storage::disk('public')->exists($tempDir)) {
                Storage::disk('public')->makeDirectory($tempDir, 0755, true);
            }

            $filename = "product_{$productId}.jpg";
            $relativePath = "{$tempDir}/{$filename}";
            $fullLocalPath = storage_path("app/public/{$relativePath}");

            // Check if already downloaded
            if (file_exists($fullLocalPath) && filesize($fullLocalPath) > 0) {
                Log::info('Image already exists locally', [
                    'product_id' => $productId,
                    'path' => $fullLocalPath
                ]);
                return $fullLocalPath;
            }

            Log::info('Downloading image', [
                'product_id' => $productId,
                'url' => $imageUrl,
                'local_path' => $fullLocalPath
            ]);

            // Download with timeout and retries
            $response = Http::timeout(15)
                ->connectTimeout(5)
                ->retry(2, 500)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; Laravel App)',
                    'Accept' => 'image/*,*/*'
                ])
                ->get($imageUrl);

            if (!$response->successful()) {
                Log::warning('HTTP request failed', [
                    'product_id' => $productId,
                    'status' => $response->status(),
                    'url' => $imageUrl
                ]);
                return null;
            }

            $imageData = $response->body();

            // Validate image data
            if (empty($imageData) || strlen($imageData) < 100) {
                Log::warning('Invalid image data received', [
                    'product_id' => $productId,
                    'data_length' => strlen($imageData ?? '')
                ]);
                return null;
            }

            // Save to storage
            $saved = Storage::disk('public')->put($relativePath, $imageData);

            if (!$saved) {
                Log::error('Failed to save image to storage', [
                    'product_id' => $productId,
                    'path' => $relativePath
                ]);
                return null;
            }

            // Verify file was created
            if (!file_exists($fullLocalPath) || filesize($fullLocalPath) === 0) {
                Log::error('File verification failed after save', [
                    'product_id' => $productId,
                    'path' => $fullLocalPath,
                    'exists' => file_exists($fullLocalPath),
                    'size' => file_exists($fullLocalPath) ? filesize($fullLocalPath) : 0
                ]);
                return null;
            }

            Log::info('Image downloaded and saved successfully', [
                'product_id' => $productId,
                'path' => $fullLocalPath,
                'size' => filesize($fullLocalPath)
            ]);

            return $fullLocalPath;

        } catch (Exception $e) {
            Log::error('Exception during image download', [
                'product_id' => $productId,
                'url' => $imageUrl,
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
            return null;
        }
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        Log::info('Starting QuotePlacedMail toMail method', [
            'quote_id' => $this->quote->id ?? 'unknown',
            'notifiable_type' => get_class($notifiable),
            'memory_usage' => memory_get_usage(true)
        ]);

        try {

            $backendURL = config('app.backend_url');
            $logoUrl = public_path((config('app.website') == 'UAE' ? 'uae_logo.png' : 'us_logo.png'));

            $companyName = config('app.website') == 'UAE' ? 'THE HORECA STORE INC' : 'THE HORECA STORE INC';
            $street = config('app.website') == 'UAE' ? '8800 Bissonnet Street, Ste A,' : '8800 Bissonnet Street, Ste A,';
            $city = config('app.website') == 'UAE' ? 'Houston, Texas 77074' : 'Houston, Texas 77074';
            $phone = config('app.website') == 'UAE' ? '1 (866) 446-7322' : '1 (866) 446-7322';
            $siteEmail = config('app.website') == 'UAE' ? 'hello@horecastore.ae':'sales@thehorecastore.com';
            $siteURL = url('/');

            $name = $notifiable->type === 'Private' ? $notifiable->name : $notifiable->business_name;
            $customerAddress = $this->quote->customerAddress;
            $address = $customerAddress->address ?? '';
            $customerCity = $customerAddress->city ?? '';
            $country = $customerAddress->country ?? '';
            $email = $notifiable->email ?? '';

            $createdAt = $this->quote->created_at->format('M d Y');
            $expiredAt = $this->quote->created_at->copy()->addDays($this->quote->expiration_days)->format('M d Y');
            $quoteNumber = $this->quote->quote_number;
            $paymentMode = $this->quote->payment_terms;
            $quoteType = 'Online';
            $currency = config('app.website') == 'UAE' ? 'AED' : '$';

            Log::info('Processing products for PDF', [
                'quote_id' => $this->quote->id,
                'product_count' => $this->quote->quoteProducts->count()
            ]);

            $products = $this->processProducts($currency);

            $subTotal = number_format($this->quote->amount ?? 0, 2, '.', ',');
            $shippingCharge = number_format($this->quote->shipping_charge ?? 0, 2, '.', ',');
            $taxName = config('app.website') == 'UAE' ? 'VAT' : 'Sales Tax';
            $taxPercent = $this->quote->tax_percentage;
            $taxAmount = number_format($this->quote->tax_amount ?? 0, 2, '.', ',');
            $total = number_format($this->quote->total_amount ?? 0, 2, '.', ',');

            $totalInWords = config('app.website') == 'UAE'
                ? convertNumberToWords($total, "AED", "Fils")
                : convertNumberToWords($total, "U.S. Dollars", "Cents");

            $beneficiaryAddress = config('app.website') == 'UAE' ? '8800 BISSONNET ST STE A, HOUSTON TX 77074-2435' : '8800 BISSONNET ST STE A, HOUSTON TX 77074-2435';
            $accountNo = config('app.website') == 'UAE' ? '6130 9953 3' : '6130 9953 3';
            $bankName = config('app.website') == 'UAE' ? 'JP Morgan Chase Bank' : 'JP Morgan Chase Bank';
            $routingCode = config('app.website') == 'UAE' ? '1110 0061 4' : '1110 0061 4';

            $pdfParams = [
                'logoUrl' => $logoUrl,
                'companyName' => $companyName,
                'street' => $street,
                'city' => $city,
                'phone' => $phone,
                'siteEmail' => $siteEmail,
                'siteURL' => $siteURL,
                'name' => $name,
                'address' => $address,
                'city' => $customerCity,
                'country' => $country,
                'email' => $email,
                'createdAt' => $createdAt,
                'expiredAt' => $expiredAt,
                'quoteNumber' => $quoteNumber,
                'paymentMode' => $paymentMode,
                'quoteType' => $quoteType,
                'currency' => $currency,
                'products' => $products,
                'subTotal' => $subTotal,
                'shippingCharge' => $shippingCharge,
                'taxName' => $taxName,
                'taxPercent' => $taxPercent,
                'taxAmount' => $taxAmount,
                'total' => $total,
                'totalInWords' => $totalInWords,
                'beneficiaryAddress' => $beneficiaryAddress,
                'accountNo' => $accountNo,
                'bankName' => $bankName,
                'routingCode' => $routingCode,
            ];

            $rightPngURL = $backendURL. '/right.png';
            $mailIconURL = $backendURL. '/right.png';

            $siteName = config('app.website') == 'UAE' ? 'HorecaStore.ae':'Thehorecastore.com';
            $downloadLink = url('/my-quotes');
            $orderLink = url('/checkout');
            $mailParams = [
                'logoUrl' => $logoUrl,
                'name' => $name,
                'rightPngURL' => $rightPngURL,
                'mailIconURL' => $mailIconURL,
                'downloadLink' => $downloadLink,
                'orderLink' => $orderLink,
                'siteName' => $siteName,
                'siteEmail' => $siteEmail,
            ];

            Log::info('Generating PDF', ['quote_id' => $this->quote->id]);
            $pdf = $this->generatePDF($pdfParams);

            Log::info('QuotePlacedMail completed successfully', [
                'quote_id' => $this->quote->id,
                'memory_final' => memory_get_usage(true)
            ]);

            return (new MailMessage)
                ->subject("Your HorecaStore Quote #{$quoteNumber} Has Been Successfully Placed")
                ->attachData($pdf->output(), "Quote_{$quoteNumber}.pdf", [
                    'mime' => 'application/pdf',
                ])
                ->markdown('emails.quotes.quote-placed', $mailParams);

        } catch (Exception $e) {
            Log::error('Error in QuotePlacedMail toMail method', [
                'quote_id' => $this->quote->id ?? 'unknown',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Process products with better error handling
     */
    private function processProducts($currency)
    {
        $products = collect();

        try {
            foreach ($this->quote->quoteProducts as $index => $quoteProduct) {
                try {
                    $productSupplierDetail = $quoteProduct->vendorProductSupplier;
                    $productDetail = $quoteProduct->product;

                    if (!$productDetail) {
                        Log::warning('Product detail not found for quote product', [
                            'quote_id' => $this->quote->id,
                            'quote_product_id' => $quoteProduct->id
                        ]);
                        continue;
                    }

                    $product = new \stdClass();
                    $product->count = $index + 1;
                    $product->name = $productDetail->name ?? 'Unknown Product';
                    $product->brandName = $productDetail->brand->name ?? 'Unknown Brand';
                    $product->sku = $productDetail->sku ?? 'N/A';
                    $product->warrantyInfo = $productSupplierDetail->warranty_information ?? 'N/A';
                    $product->shippingCharge = $quoteProduct->shipping_charge == 0
                        ? 'FREE SHIPPING'
                        : $currency . ' ' . number_format($quoteProduct->shipping_charge, 2, '.', ',');

                    $product->deliveryDays = $productSupplierDetail->delivery_days ?? 'N/A';
                    $product->productURL = url('/product/' . $productDetail->id);

                    // Use pre-downloaded images or fallback
                    $product->localImagePath = $this->getPreDownloadedImagePath($productDetail);

                    $product->quantity = (int) $quoteProduct->quantity;

                    $fullValue = $productDetail->sellingUnitAttribute->attribute_value ?? '';
                    $product->sellingType = $productDetail->sellingUnitAttribute && $fullValue
                        ? (strpos($fullValue, '/') !== false
                            ? trim(explode('/', $fullValue)[1])
                            : trim($fullValue))
                        : 'Each';

                    $product->unitPrice = number_format($quoteProduct->unit_price, 2, '.', ',');
                    $product->total = number_format($quoteProduct->amount, 2, '.', ',');

                    $products->push($product);

                } catch (Exception $e) {
                    Log::warning('Error processing individual product for quote', [
                        'quote_id' => $this->quote->id,
                        'product_index' => $index,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
        } catch (Exception $e) {
            Log::error('Error processing products for quote', [
                'quote_id' => $this->quote->id,
                'error' => $e->getMessage()
            ]);
            return collect();
        }

        return $products;
    }

    /**
     * Get path to pre-downloaded image
     */
    private function getPreDownloadedImagePath($productDetail)
    {
        try {
            $filename = "product_{$productDetail->id}.jpg";
            $relativePath = "temp/quotes/{$this->quote->id}/{$filename}";
            $fullPath = storage_path("app/public/{$relativePath}");

            if (file_exists($fullPath) && filesize($fullPath) > 0) {
                Log::info('Using pre-downloaded image', [
                    'product_id' => $productDetail->id,
                    'path' => $fullPath
                ]);
                return $fullPath;
            }

            // Fallback: try to find any existing temp image
            $tempBasePath = storage_path('app/public/temp');
            $pattern = $tempBasePath . "/**/product_{$productDetail->id}.*";
            $files = glob($pattern, GLOB_BRACE);

            foreach ($files as $file) {
                if (is_file($file) && filesize($file) > 0) {
                    Log::info('Using existing temp image', [
                        'product_id' => $productDetail->id,
                        'path' => $file
                    ]);
                    return $file;
                }
            }

            // Last resort: use dummy image or return null
            $dummyPath = $this->getDummyImagePath();
            if ($dummyPath) {
                Log::info('Using dummy image', [
                    'product_id' => $productDetail->id,
                    'path' => $dummyPath
                ]);
                return $dummyPath;
            }

            Log::info('No image found for product', [
                'product_id' => $productDetail->id
            ]);
            return null;

        } catch (Exception $e) {
            Log::warning('Error getting pre-downloaded image path', [
                'product_id' => $productDetail->id ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get dummy image path
     */
    private function getDummyImagePath()
    {
        $paths = [
            public_path('images/product-placeholder.jpg'),
            public_path('assets/images/no-image.jpg'),
            storage_path('app/public/dummy-product.jpg')
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Generate PDF with error handling
     */
    private function generatePDF($pdfParams)
    {
        try {
            return Pdf::loadView('pdf.quote', $pdfParams);
        } catch (Exception $e) {
            Log::error('Error generating PDF for quote', [
                'quote_id' => $this->quote->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Enhanced cleanup method for temporary files
     */
    public function cleanupTempFiles()
    {
        try {
            $tempBasePath = storage_path('app/public/temp');
            if (!is_dir($tempBasePath)) {
                return;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tempBasePath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            $now = time();
            $maxAge = 3600; // 1 hour

            foreach ($iterator as $file) {
                if ($file->isFile() && $now - $file->getMTime() >= $maxAge) {
                    @unlink($file->getRealPath());
                } elseif ($file->isDir() && $now - $file->getMTime() >= $maxAge) {
                    @rmdir($file->getRealPath());
                }
            }

        } catch (Exception $e) {
            Log::info('Cleanup temp files error (non-critical)', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Destructor for cleanup
     */
    public function __destruct()
    {
        $this->cleanupTempFiles();
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}