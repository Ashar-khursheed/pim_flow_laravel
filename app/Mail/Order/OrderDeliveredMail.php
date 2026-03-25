<?php

namespace App\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;
use App\Models\FrontEnd\Order;
use App\Helpers\CurrencyConverter;

class OrderDeliveredMail extends Mailable
{
	use Queueable, SerializesModels;

	public $order;

	public function __construct(Order $order)
	{
		$this->order = $order;
	}

	public function build()
	{
		$order = $this->order;
		$isUAE = in_array(config('app.website'), ['UAE', 'UAE_T']); /* Resolved once — used throughout */
		$isUS = in_array(config('app.website'), ['US', 'US_T']);

		$backendURL = config('app.backend_url');
		$logoUrl = $backendURL . '/logo.png';
		$rightPngURL = $backendURL . '/right.png';
		$name = $order->customer->name ?? 'User';
		$orderNumber = $order->order_number;

		/* Resolve source and target currency */
		$sourceCurrencySymbol = $isUAE ? 'AED' : '$';
		$sourceCurrencyTitle = $isUAE ? 'AED' : 'USD';
		$customerAddress = $order->customerAddress;
		$currency = $customerAddress->relatedCountry->currency->symbol ?? $sourceCurrencySymbol;
		$targetCurrencyTitle = $customerAddress->relatedCountry->currency->title ?? $sourceCurrencyTitle;
		$currencyConversionRate = CurrencyConverter::getRate($sourceCurrencyTitle, $targetCurrencyTitle) ?? 1; /* Fallback to 1 if rate unavailable */

		/* Build products collection */
		$products = collect();

		foreach ($order->orderProducts as $orderProduct) {
			$productDetail = $orderProduct->product;
			if (!$productDetail) continue;

			$images = is_array($productDetail->images) ? $productDetail->images : (is_array($decoded = json_decode($productDetail->images, true)) ? $decoded : null);

			/* Resolve shipping charge with US state logic */
			$productShipping = $orderProduct->shipping_charge ?? 0;

			$product = new \stdClass();
			$product->image = $images[0] ?? null;
			$product->name = $productDetail->name;
			$product->quantity = (int) $orderProduct->quantity;
			$product->shippingCharge = $productShipping * $currencyConversionRate;
			$product->total = $orderProduct->amount * $currencyConversionRate;

			$products->push($product);
		}

		/* Site identity based on deployment */
		$siteUrl = match (config('app.website')) {
			'UAE' => 'HorecaStore.ae',
			default => 'Thehorecastore.com',
		};

		$siteEmail = match (config('app.website')) {
			'UAE' => 'hello@horecastore.ae',
			'US_T' => 'test_us@thehorecastore.co',
			'UAE_T' => 'test_uae@thehorecastore.co',
			default => 'sales@thehorecastore.com',
		};

		$params = [
			'logoUrl' => $logoUrl,
			'name' => $name,
			'orderNumber' => $orderNumber,
			'currency' => $currency,
			'products' => $products,
			'rightPngURL' => $rightPngURL,
			'checkoutURL' => url("/view-order/{$order->id}"),
			'orderDetailUrl' => url("/view-order/{$order->id}"),
			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		return $this->subject("Your HorecaStore Order #{$orderNumber} Has Been Delivered")
		->markdown('emails.orders.order-delivered')
		->with($params);
	}
}