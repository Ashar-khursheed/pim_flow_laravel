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

	public $randomPassword;

	public function __construct($order)
	{
		dd($order->orderProducts->toArray());
		$this->order = $randomPassword;
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

		$address = $this->order->customerAddress->address ?? '';
		$city = $this->order->customerAddress->city->name ?? '';
		$country = $this->order->customerAddress->country->name ?? '';
		$zipcode = $this->order->customerAddress->zip_code ?? '';

		$products = collect();

		foreach ($this->order->orderProducts as $orderProduct) {
			$productDetail = $orderProduct->product;

			if ($productDetail) {
				$product = new \stdClass();

				$product->image = json_decode($productDetail->images)[0] ?? null;
				$product->name = $productDetail->name;
				$product->salePrice = $productDetail->sale_price;
				$product->price = $productDetail->price;
				$product->actualPrice = $product->salePrice ?? $product->price;
				$product->quantity = $orderProduct->quantity;
				$product->total = $product->actualPrice * $product->quantity;

				/* Calculate discount */
				if ($product->salePrice && $product->price && $product->price > 0) {
					$product->discount = round((($product->price - $product->salePrice) / $product->price) * 100, 2);
				} else {
					$product->discount = 0;
				}

				$products->push($product);
			}
		}

		$subTotal = $products->sum('total');
		$shippingCharge = $this->order->shipping_charge ?? 0;
		$vat = round(($subTotal * 0.05), 2);
		$total = $subTotal + $shippingCharge + $vat;

		$randomPassword = $this->randomPassword ?? 'User';
		$resetPasswordUrl = url("/");
		$websiteUrl = url("/");

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
				'subTotal' => $subTotal,
				'shippingCharge' => $shippingCharge,
				'vat' => $vat,
				'total' => $total,
				'randomPassword' => $randomPassword,
				'resetPasswordUrl' => $resetPasswordUrl,
				'websiteUrl' => $websiteUrl,
			];

			dd($params);

		return (new MailMessage)
			->subject('Welcome Email')
			->markdown('emails.guest-welcome', $params);
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
