<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Carbon\Carbon;

class QuotePlacedMail extends Notification implements ShouldQueue
{
	use Queueable;

	public $quote;

	public function __construct($quote)
	{
		$this->quote = $quote;
	}

	/**
	 * Get the notification's delivery channels.
	 *
	 * @return array<int, string>
	 */
	public function via($notifiable)
	{
		return ['mail'];
	}

	/**
	 * Get the mail representation of the notification.
	 */
	public function toMail($notifiable)
	{
		$backendURL = config('app.backend_url');
		$logoUrl = $backendURL . (config('app.website') == 'UAE' ? '/uae_logo.png' : '/us_logo.png');

		$companyName = config('app.website') == 'UAE' ? 'THE HORECA STORE INC' : 'THE HORECA STORE INC';
		$street = config('app.website') == 'UAE' ? '8800 Bissonnet Street, Ste A,' : '8800 Bissonnet Street, Ste A,';
		$city = config('app.website') == 'UAE' ? 'Houston, Texas 77074' : 'Houston, Texas 77074';
		$phone = config('app.website') == 'UAE' ? '1 (866) 446-7322' : '1 (866) 446-7322';
		$siteEmail = config('app.website') == 'UAE' ? 'hello@horecastore.ae':'sales@thehorecastore.com';
		$siteURL = $url;

		$name = $notifiable->type === 'Private' ? $notifiable->name : $notifiable->business_name;

		$customerAddress = $this->quote->customerAddress;
		$address = $customerAddress->address ?? '';
		$city = $customerAddress->city ?? '';
		$country = $customerAddress->country ?? '';

		$email = $notifiable->email ?? '';

		$createdAt = $this->quote->created_at->format('M d Y');
		$expiredAt = $this->quote->created_at->copy()->addDays($this->quote->expiration_days)->format('M d Y');
		$quoteNumber = $this->quote->quote_number;

		$paymentMode = $this->quote->payment_terms;
		$quoteType = 'Online';
		$currency = config('app.website') == 'UAE' ? 'AED' : '$';

		$products = collect();
		foreach ($this->quote->quoteProducts as $index => $quoteProduct) {
			$productSupplierDetail = $quoteProduct->vendorProductSupplier;
			$productDetail = $quoteProduct->product;

			if ($productDetail) {
				$product = new \stdClass();
				$product->count = $index + 1;
				$product->name = $productDetail->name;
				$product->brandName = $productDetail->brand->name ?? null;
				$product->sku = $productDetail->sku;
				$product->warrantyInfo = $productSupplierDetail->warranty_information ?? null;
				$product->shippingCharge = $quoteProduct->shipping_charge == 0
				? 'FREE SHIPPING'
				: $currency . ' ' . number_format($quoteProduct->shipping_charge, 2, '.', ',');

				$product->deliveryDays = $productSupplierDetail->delivery_days ?? null;
				$product->productURL = url('/product/' . $productDetail->id);

				$images = is_array($productDetail->images)
				? $productDetail->images
				: (is_array($decoded = json_decode($productDetail->images, true)) ? $decoded : null);

				$product->image = is_array($images) ? ($images[0] ?? null) : null;

				$product->quantity = (int) $quoteProduct->quantity;

				$fullValue = $productDetail->sellingUnitAttribute->attribute_value ?? '';
				$product->sellingType = $productDetail->sellingUnitAttribute && $fullValue
				? (strpos($fullValue, '/') !== false
					? trim(explode('/', $fullValue)[1])
					: trim($fullValue))
				: '';

				$product->unitPrice = number_format($quoteProduct->unit_price, 2, '.', ',');
				$product->total = number_format($quoteProduct->amount, 2, '.', ',');

				$products->push($product);
			}
		}

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
		$routingCode = config('app.website') == 'UAE' ? '	1110 0061 4' : '	1110 0061 4';

		$params = [
			'logoUrl' => $logoUrl,
			'name' => $name,
			'quoteUrl' => $quoteUrl,

			'quoteNumber' => $quoteNumber,
			'quoteDate' => $quoteDate,
			'currency' => $currency,
			'paidAmount' => $paidAmount,
			'paymentMethod' => $paymentMethod,

			'address' => $address,
			'city' => $city,
			'country' => $country,
			'zipcode' => $zipcode,

			'products' => $products,
			'totalSaved' => $totalSaved,

			'subTotal' => $subTotal,
			'shippingCharge' => $shippingCharge,
			'taxName' => $taxName,
			'taxPercent' => $taxPercent,
			'taxAmount' => $taxAmount,
			'total' => $total,

			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		return (new MailMessage)
		->subject("Your HorecaStore Quote #{$quoteNumber} Has Been Successfully Placed")
		->markdown('emails.quotes.quote-placed', $params);
	}


	/**
	 * Get the array representation of the notification.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(object $notifiable): array
	{
		return [
			//
		];
	}
}
