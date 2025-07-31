<?php

namespace App\Notifications\Orders;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Carbon\Carbon;

class OrderPlacedMail extends Notification implements ShouldQueue
{
	use Queueable;

	public $order;

	public function __construct($order)
	{
		$this->order = $order;
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
		$name = $notifiable->name ?? 'User';
		$orderUrl = url("/my-order");

		$orderNumber = $this->order->order_number;
		$orderDate = Carbon::parse($this->order->created_at)->format('D, M d, Y');
		$currency = config('app.website') == 'UAE' ? 'AED' : '$';
		$paidAmount = $this->order->paid_amount ?? 0;
		$paymentMethod = optional($this->order->payments()->latest()->first())->payment_mode ?? 'Cash On Delivery';

		$customerAddress = $this->order->customerAddress;
		$address = $customerAddress->address ?? '';
		$city = $customerAddress->city ?? '';
		$country = $customerAddress->country ?? '';
		$zipcode = $customerAddress->zip_code ?? '';

		$products = collect();

		foreach ($this->order->orderProducts as $orderProduct) {
			$productSupplierDetail = $orderProduct->vendorProductSupplier;
			$productDetail = $orderProduct->product;

			if ($productDetail) {
				$product = new \stdClass();

				$images = is_array($productDetail->images) ? $productDetail->images : (is_array($decoded = json_decode($productDetail->images, true)) ? $decoded : null);
				$product->image = is_array($images) ? ($images[0] ?? null) : null;
				$product->name = $productDetail->name;
				$product->expectedShippingDate = $productSupplierDetail
				? getDateRange($this->order->created_at, $productSupplierDetail->delivery_days)
				: null;

				/* FIXED: Safe numeric conversion and validation */
				$supplierPrice = $this->toNumeric($productSupplierDetail->price ?? 0);
				$unitPrice = $this->toNumeric($orderProduct->unit_price ?? 0);

				/* Original Price (before discount) */
				$originalPrice = $supplierPrice > 0 ? $supplierPrice : $unitPrice;


				// ✅ Ensure all numeric values are safely converted
				$product->priceBeforeDiscount = $this->toNumeric($originalPrice);
				$product->unitPrice = $unitPrice;


				/* FIXED: Safe discount calculation with proper validation */
				if (
					$productSupplierDetail &&
					$supplierPrice > 0 &&
					$unitPrice > 0 &&
					$supplierPrice > $unitPrice
				) {
					$product->discount = round((($supplierPrice - $unitPrice) / $supplierPrice) * 100, 2);
				} else {
					$product->discount = 0;
				}

				$product->quantity = (int) ($orderProduct->qty ?? 1);
				$product->total = $this->toNumeric($orderProduct->amount ?? ($unitPrice * $product->quantity));



				$products->push($product);
			}
		}

		/* FIXED: Safe calculation for total price before discount */
		$totalPriceWithoutDiscount = $products->sum(function ($p) {
			$price = $this->toNumeric($p->priceBeforeDiscount ?? 0);
			$quantity = (int) ($p->quantity ?? 0);
			return $price * $quantity;
		});

		/* FIXED: Safe calculation for total saved */
		$orderAmount = $this->toNumeric($this->order->amount ?? 0);
		$totalSaved = max(0, $totalPriceWithoutDiscount - $orderAmount);

		/* FIXED: Safe numeric conversions for all order amounts */
		$subTotal = $this->toNumeric($this->order->amount ?? 0);
		$shippingCharge = $this->toNumeric($this->order->shipping_charge ?? 0);
		$taxName = config('app.website') == 'UAE' ? 'VAT' : 'SALES TAX';
		$taxPercent = $this->toNumeric($this->order->tax_percentage ?? 0);
		$taxAmount = $this->toNumeric($this->order->tax_amount ?? 0);
		$total = $this->toNumeric($this->order->total_amount ?? 0);

		$siteUrl = config('app.website') == 'UAE' ? 'HorecaStore.ae':'Thehorecastore.com';
		$siteEmail = config('app.website') == 'UAE' ? 'hello@horecastore.ae':'sales@thehorecastore.com';

		$params = [
			'logoUrl' => $logoUrl,
			'name' => $name,
			'orderUrl' => $orderUrl,

			'orderNumber' => $orderNumber,
			'orderDate' => $orderDate,
			'currency' => $currency,
			'paidAmount' => $this->toNumeric($paidAmount),
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
		->subject("Your HorecaStore Order #{$orderNumber} Has Been Successfully Placed")
		->markdown('emails.orders.order-placed', $params);
		$htmlContent = view('emails.orders.order-placed', $params)->render();
		\Log::info('Rendered Order Email HTML', ['order_id' => $this->order->id, 'html' => $htmlContent]);

	}

	/**
	 * ADDED: Helper method to safely convert values to numeric
	 */
	private function toNumeric($value)
	{
		if (is_null($value) || $value === '' || $value === false) {
			return 0;
		}
		
		if (is_numeric($value)) {
			return (float) $value;
		}
		
		// Try to extract numeric value from string
		$cleaned = preg_replace('/[^0-9.-]/', '', (string) $value);
		
		if (is_numeric($cleaned)) {
			return (float) $cleaned;
		}
		
		// Log problematic values for debugging
		\Log::warning("Non-numeric value encountered in OrderPlacedMail", [
			'original_value' => $value,
			'type' => gettype($value),
			'order_id' => $this->order->id ?? 'unknown'
		]);
		
		return 0;
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