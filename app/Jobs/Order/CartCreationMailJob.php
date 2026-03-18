<?php
namespace App\Jobs\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Mail;
use App\Models\FrontEnd\CustomerCart;
use App\Mail\Order\CartCreationMail;
use App\Helpers\CurrencyConverter;

class CartCreationMailJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

	public $customerCartID;
	public $randomPassword;
	public $isNewCustomer;

	public function __construct($data)
	{
		$this->customerCartID = $data['recordId'];
		$this->randomPassword = $data['randomPassword'];
		$this->isNewCustomer  = $data['isNewCustomer'];
	}

	public function middleware(): array
	{
		return [new RateLimited('emails')];
	}

	public function handle(): void
	{
		$customerCart = CustomerCart::find($this->customerCartID);
		if (!$customerCart) {
			$this->fail(new \Exception("Customer cart {$this->customerCartID} not found"));
			return;
		}

		// ✅ Resolve currency here in the Job, not inside the Mailable
		$sourceCurrencySymbol = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 'AED' : '$';
		$sourceCurrencyTitle  = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 'AED' : 'USD';
		$customerAddress      = $customerCart->customerAddress;
		$currency             = $customerAddress->relatedCountry->currency->symbol ?? $sourceCurrencySymbol;
		$targetCurrencyTitle  = $customerAddress->relatedCountry->currency->title  ?? $sourceCurrencyTitle;
		$currencyConversionRate = ($sourceCurrencyTitle === $targetCurrencyTitle)
		? 1.0
		: CurrencyConverter::getRate($sourceCurrencyTitle, $targetCurrencyTitle);

		\Log::info('CartCreationMailJob Currency', [
			'customerCartID'        => $this->customerCartID,
			'sourceCurrencyTitle'   => $sourceCurrencyTitle,
			'targetCurrencyTitle'   => $targetCurrencyTitle,
			'currency'              => $currency,
			'currencyConversionRate'=> $currencyConversionRate,
		]);

		$fromEmail = match (config('app.website')) {
			'US'    => 'sales@thehorecastore.com',
			'UAE'   => 'hello@horecastore.ae',
			'US_T'  => 'test_us@thehorecastore.co',
			'UAE_T' => 'test_uae@thehorecastore.co',
			default => 'test@thehorecastore.co',
		};
		$fromName    = 'HorecaStore Cart Updates';
		$replyToEmail = $fromEmail;

		// ✅ Pass $currencyConversionRate into the Mailable
		Mail::to($customerCart->customer->email)->send(
			(new CartCreationMail($customerCart, $this->randomPassword, $this->isNewCustomer, $currencyConversionRate))
			->from($fromEmail, $fromName)
			->replyTo($replyToEmail)
		);

		if (in_array(config('app.website'), ['US', 'US_T'])) {
			$recipients = order_cc_mails();
			$to  = array_shift($recipients);
			$cc  = $recipients;
			Mail::to($to)->cc($cc)->send(
				(new CartCreationMail($customerCart, $this->randomPassword, $this->isNewCustomer, $currencyConversionRate))
				->from($fromEmail, $fromName)
				->replyTo($replyToEmail)
			);
		}
	}

	public function failed(\Throwable $exception): void
	{
		$jobName = class_basename($this);
		$errorDetails = [
			'job'     => $jobName,
			'message' => $exception->getMessage(),
			'file'    => $exception->getFile(),
			'line'    => $exception->getLine(),
			'trace'   => $exception->getTraceAsString(),
		];
		logger()->error("{$jobName} failed", $errorDetails);
	}
}