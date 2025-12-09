<?php

namespace App\Mail\Quote;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

use App\Models\MadeToOrder;
use App\Models\Product;

class RequestQuoteMail extends Mailable
{
	use Queueable, SerializesModels;

	public $reqQuote;

	/**
	 * Create a new message instance.
	 */
	public function __construct(MadeToOrder $reqQuote)
	{
		$this->reqQuote = $reqQuote;
	}

	public function build()
	{
		$reqQuote = $this->reqQuote;

		$backendURL = config('app.backend_url');
		$logoUrl = $backendURL . '/logo.png';

		$name = $reqQuote->name ?? 'User';
		$email = $reqQuote->email ?? 'User';
		$phone = $reqQuote->phone_number ?? 'User';

		$address = $reqQuote->address ?? '';
		$city = $reqQuote->city ?? '';
		$state = $reqQuote->state ?? '';
		$country = $reqQuote->country ?? '';
		$zipcode = $reqQuote->zipcode ?? '';

		$product = new \stdClass();
		$productDetail = Product::find($reqQuote->product_id);if ($productDetail) {
			$images = is_array($productDetail->images)
			? $productDetail->images
			: (is_array($decoded = json_decode($productDetail->images, true)) ? $decoded : null);
			$product->image = is_array($images) ? ($images[0] ?? null) : null;
			$product->name = $productDetail->name;
			$product->sku = $productDetail->sku;
			$product->quantity = (int) $reqQuote->quantity;
		}

		$notes = $reqQuote->notes ?? '';

		$siteUrl = match (config('app.website')) {
			'US'  => 'Thehorecastore.com',
			'UAE'  => 'HorecaStore.ae',
			'TEST' => 'Thehorecastore.com',
			default => 'Thehorecastore.com',
		};

		$siteEmail = match (config('app.website')) {
			'US'  => 'orders@thehorecastore.com',
			'UAE'  => 'orders@horecastore.ae',
			'US_T' => 'test_us@thehorecastore.co',
			'UAE_T' => 'test_uae@thehorecastore.co',
			default => 'test@thehorecastore.co',
		};

		$params = [
			'logoUrl' => $logoUrl,
			'name' => $name,
			'email' => $email,
			'phone' => $phone,

			'address' => $address,
			'city' => $city,
			'state' => $state,
			'country' => $country,
			'zipcode' => $zipcode,

			'product' => $product,
			'notes' => $notes,

			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		return $this->subject("Your Quote Request Has Been Received – We’ll Get Back to You Soon!")
		->markdown('emails.quotes.request-quote')
		->with($params);
	}
}
