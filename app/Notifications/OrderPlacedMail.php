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

		$paidAmount = $this->order->paid_amount;
		$orderId = $this->order->order_number;
		$orderDate = Carbon::parse($this->order->created_at)->format('d/M/Y');
		$paymentMethod = "COD";

		/* Defensive null checks */
		$customerAddress = $this->order->customerAddress;
		$address = $customerAddress->address ?? '';
		$city = $customerAddress->city ?? '';
		$country = $customerAddress->country ?? '';
		$zipcode = $customerAddress->zip_code ?? '';

		$products = collect();

		foreach ($this->order->orderProducts as $orderProduct) {
			$productDetail = $orderProduct->product;

			if ($productDetail) {
				$product = new \stdClass();

				/* Ensure images is valid JSON array */
				$images = json_decode($productDetail->images, true);
				$product->image = is_array($images) ? ($images[0] ?? null) : null;

				$product->name = $productDetail->name;
				$product->price = $productDetail->price;
				$product->salePrice = $productDetail->sale_price;

				$product->actualPrice = $product->salePrice ?? $product->price;
				$product->quantity = $orderProduct->quantity;

				$product->priceTotal = $product->price * $product->quantity;
				$product->total = $product->actualPrice * $product->quantity;

				/* Discount calculation */
				if ($product->salePrice && $product->price && $product->price > 0) {
					$product->discount = round((($product->price - $product->salePrice) / $product->price) * 100, 2);
				} else {
					$product->discount = 0;
				}

				$products->push($product);
			}
		}

		$subTotal = $products->sum('total');
		$totalPriceWithoutDiscount = $products->sum('priceTotal');
		$totalSaved = max(0, $totalPriceWithoutDiscount - $subTotal);

		$shippingCharge = $this->order->shipping_charge ?? 0;
		$vat = round(($subTotal * 0.05), 2);
		$total = $subTotal + $shippingCharge + $vat;

		$params = [
			'logoUrl' => $logoUrl,
			'name' => $name,
			'orderUrl' => $orderUrl,
			'paidAmount' => $paidAmount,
			'orderId' => $orderId,
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
