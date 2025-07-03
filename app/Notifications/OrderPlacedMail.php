<?php

namespace App\Notifications;

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
		$logoUrl = config('app.logo_url');
		$name = $notifiable->name ?? 'User';
		$orderUrl = url("/registration/all-orders");
		$currency = env('APP_WEBSITE') == 'UAE' ? 'AED' : 'USD';

		$paidAmount = number_format($this->order->paid_amount ?? 0, 2, '.', '');
		$orderNumber = $this->order->order_number;
		$orderDate = Carbon::parse($this->order->created_at)->format('d/M/Y');
		$paymentMethod = "COD";

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

				$images = json_decode($productDetail->images, true);
				$product->image = is_array($images) ? ($images[0] ?? null) : null;

				$product->name = $productDetail->name;
				$product->quantity = $orderProduct->quantity;

				$price = $productSupplierDetail->price ?? 0;
				$salePrice = $productSupplierDetail->sale_price ?? null;

				$product->price = number_format($price, 2, '.', '');
				$product->salePrice = $salePrice !== null ? number_format($salePrice, 2, '.', '') : null;

				$product->actualPrice = number_format($salePrice ?? $price, 2, '.', '');

				$product->priceTotal = number_format($price * $product->quantity, 2, '.', '');
				$product->total = number_format(($salePrice ?? $price) * $product->quantity, 2, '.', '');

				if ($salePrice && $price > 0) {
					$product->discount = number_format((($price - $salePrice) / $price) * 100, 2);
				} else {
					$product->discount = 0;
				}

				$product->expectedShippingDate = $productSupplierDetail
					? getDateRange($this->order->created_at, $productSupplierDetail->delivery_days)
					: null;

				$products->push($product);
			}
		}

		$subTotal = number_format($products->sum('total'), 2, '.', '');
		$totalPriceWithoutDiscount = number_format($products->sum('priceTotal'), 2, '.', '');
		$totalSaved = number_format(max(0, $totalPriceWithoutDiscount - $subTotal), 2, '.', '');

		$shippingCharge = number_format($this->order->shipping_charge ?? 0, 2, '.', '');
		$vat = number_format(((float) $subTotal * 0.05), 2, '.', '');
		$total = number_format((float) $subTotal + (float) $shippingCharge + (float) $vat, 2, '.', '');

		$params = [
			'logoUrl' => $logoUrl,
			'name' => $name,
			'orderUrl' => $orderUrl,
			'paidAmount' => $paidAmount,
			'orderNumber' => $orderNumber,
			'orderDate' => $orderDate,
			'paymentMethod' => $paymentMethod,

			'address' => $address,
			'city' => $city,
			'country' => $country,
			'zipcode' => $zipcode,

			'products' => $products,
			'totalSaved' => $totalSaved,
			'subTotal' => $subTotal,
			'shippingCharge' => $shippingCharge,
			'vat' => $vat,
			'total' => $total,
		];

		return (new MailMessage)
			->subject('Your Horeca Order Has Been Placed Successfully')
			->markdown('emails.order-placed', $params);
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
