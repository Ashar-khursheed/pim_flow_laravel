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

		$companyName = config('app.website') == 'UAE' ? 'THE HORECA STORE INC.' : 'THE HORECA STORE INC.';
		$street = config('app.website') == 'UAE' ? '8800 Bissonnet Street, Ste A,' : '8800 Bissonnet Street, Ste A,';
		$city = config('app.website') == 'UAE' ? 'Houston, Texas 77074' : 'Houston, Texas 77074';
		$phone = config('app.website') == 'UAE' ? '1 (866) 446-7322' : '1 (866) 446-7322';
		$siteEmail = config('app.website') == 'UAE' ? 'hello@horecastore.ae':'sales@thehorecastore.com';
		$siteUrl = config('app.website') == 'UAE' ? 'HorecaStore.ae':'www.thehorecastore.com';

		$name = $notifiable->type === 'Private' ? $notifiable->name : $notifiable->business_name;

		$customerAddress = $this->quote->customerAddress;
		$address = $customerAddress->address ?? '';
		$city = $customerAddress->city ?? '';
		$country = $customerAddress->country ?? '';

		$email = $notifiable->email ?? '';

		$createdAt = $this->quote->created_at->format('M d Y');



		$quoteUrl = url("/registration/all-quotes");

		$quoteNumber = $this->quote->quote_number;
		$quoteDate = Carbon::parse($this->quote->created_at)->format('D, M d, Y');
		$currency = config('app.website') == 'UAE' ? 'AED' : '$';
		$paidAmount = number_format($this->quote->paid_amount ?? 0, 2, '.', ',');
		$paymentMethod = optional($this->quote->payments()->latest()->first())->payment_mode ?? 'Cash On Delivery';

		$customerAddress = $this->quote->customerAddress;
		$address = $customerAddress->address ?? '';
		$city = $customerAddress->city ?? '';
		$country = $customerAddress->country ?? '';
		$zipcode = $customerAddress->zip_code ?? '';

		$products = collect();

		foreach ($this->quote->quoteProducts as $quoteProduct) {
			$productSupplierDetail = $quoteProduct->vendorProductSupplier;
			$productDetail = $quoteProduct->product;

			if ($productDetail) {
				$product = new \stdClass();

				$images = is_array($productDetail->images) ? $productDetail->images : (is_array($decoded = json_decode($productDetail->images, true)) ? $decoded : null);
				$product->image = is_array($images) ? ($images[0] ?? null) : null;
				$product->name = $productDetail->name;
				$product->expectedShippingDate = $productSupplierDetail
				? getDateRange($this->quote->created_at, $productSupplierDetail->delivery_days)
				: null;

				/* Original Price (before discount) */
				$originalPrice = $productSupplierDetail->price ?? $quoteProduct->unit_price;

				$product->priceBeforeDiscount = number_format($originalPrice, 2, '.', ',');
				$product->unitPrice = number_format($quoteProduct->unit_price, 2, '.', ',');

				if (
					$productSupplierDetail &&
					$productSupplierDetail->price > $quoteProduct->unit_price &&
					$productSupplierDetail->price > 0 &&
					$quoteProduct->unit_price > 0
				) {
					$product->discount = number_format(
						(($productSupplierDetail->price - $quoteProduct->unit_price) / $productSupplierDetail->price) * 100,
						2
					);
				} else {
					$product->discount = 0;
				}

				$product->quantity = (int) $quoteProduct->quantity;
				$product->total = number_format($quoteProduct->amount, 2, '.', ',');

				$products->push($product);
			}
		}

		/* Total price before discount */
		$totalPriceWithoutDiscount = number_format($products->sum(function ($p) {
			return (float) $p->priceBeforeDiscount * $p->quantity;
		}), 2, '.', ',');

		/* Total saved = original total - actual subtotal */
		$totalSaved = number_format(
			max(0, $totalPriceWithoutDiscount - ($this->quote->amount ?? 0)), 2, '.', ','
		);

		$subTotal = number_format($this->quote->amount ?? 0, 2, '.', ',');
		$shippingCharge = number_format($this->quote->shipping_charge ?? 0, 2, '.', ',');
		$taxName = config('app.website') == 'UAE' ? 'VAT' : 'Sales Tax';
		$taxPercent = $this->quote->tax_percentage;
		$taxAmount = number_format($this->quote->tax_amount ?? 0, 2, '.', ',');
		$total = number_format($this->quote->total_amount ?? 0, 2, '.', ',');

		$siteUrl = config('app.website') == 'UAE' ? 'HorecaStore.ae':'www.thehorecastore.com';
		$siteEmail = config('app.website') == 'UAE' ? 'hello@horecastore.ae':'sales@thehorecastore.com';

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
