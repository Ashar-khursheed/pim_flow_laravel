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
		$orderUrl = url("/registration/all-orders");

		$orderNumber = $this->order->order_number;
		$orderDate = Carbon::parse($this->order->created_at)->format('D, M d, Y');
		$currency = config('app.website') == 'UAE' ? 'AED' : 'USD';
		$paidAmount = number_format($this->order->paid_amount ?? 0, 2, '.', '');
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

				/* Original Price (before discount) */
				$originalPrice = $productSupplierDetail->price ?? $orderProduct->unit_price;

				$product->priceBeforeDiscount = number_format($originalPrice, 2, '.', '');
				$product->unitPrice = number_format($orderProduct->unit_price, 2, '.', '');

				if (
					$productSupplierDetail &&
					$productSupplierDetail->price > $orderProduct->unit_price &&
					$productSupplierDetail->price > 0 &&
					$orderProduct->unit_price > 0
				) {
					$product->discount = number_format(
						(($productSupplierDetail->price - $orderProduct->unit_price) / $productSupplierDetail->price) * 100,
						2
					);
				} else {
					$product->discount = 0;
				}

				$product->quantity = (int) $orderProduct->quantity;
				$product->total = number_format($orderProduct->amount, 2, '.', '');

				$products->push($product);
			}
		}

		/* Total price before discount */
		$totalPriceWithoutDiscount = number_format($products->sum(function ($p) {
			return (float) $p->priceBeforeDiscount * $p->quantity;
		}), 2, '.', '');

		/* Total saved = original total - actual subtotal */
		$totalSaved = number_format(
			max(0, $totalPriceWithoutDiscount - ($this->order->amount ?? 0)), 2, '.', ''
		);

		$subTotal = number_format($this->order->amount ?? 0, 2, '.', '');
		$shippingCharge = number_format($this->order->shipping_charge ?? 0, 2, '.', '');
		$taxAmount = number_format($this->order->tax_amount ?? 0, 2, '.', '');
		$total = number_format($this->order->total_amount ?? 0, 2, '.', '');

		$params = [
			'logoUrl' => $logoUrl,
			'name' => $name,
			'orderUrl' => $orderUrl,

			'orderNumber' => $orderNumber,
			'orderDate' => $orderDate,
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
			'taxAmount' => $taxAmount,
			'total' => $total,
		];

		return (new MailMessage)
		->subject('Your Horeca Order Has Been Placed Successfully')
		->markdown('emails.orders.order-placed', $params);
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
