<?php

namespace App\Notifications\Orders;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Carbon\Carbon;
use App\Models\FrontEnd\Order;

class OrderPlacedNotification extends Notification implements ShouldQueue
{
	use Queueable;

	public $orderId;

	public function __construct($order_id)
	{
		$this->orderId = $order_id;
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
		$order = Order::find($this->orderId);

		$backendURL = config('app.backend_url');
		$logoUrl = $backendURL . (config('app.website') == 'UAE' ? '/uae_logo.png' : '/us_logo.png');
		$name = $notifiable->name ?? 'User';
		$orderUrl = url("/my-order");

		$orderNumber = $order->order_number;
		$orderDate = Carbon::parse($order->created_at)->format('D, M d, Y');
		$currency = config('app.website') == 'UAE' ? 'AED' : '$';
		$paidAmount = $order->paid_amount ?? 0;
		$paymentMethod = optional($order->payments()->latest()->first())->payment_mode ?? 'Cash On Delivery';

		$customerAddress = $order->customerAddress;
		$address = $customerAddress->address ?? '';
		$city = $customerAddress->city ?? '';
		$country = $customerAddress->country ?? '';
		$zipcode = $customerAddress->zip_code ?? '';

		$products = collect();

		foreach ($order->orderProducts as $orderProduct) {
			$productSupplierDetail = $orderProduct->vendorProductSupplier;
			$productDetail = $orderProduct->product;

			if ($productDetail) {
				$product = new \stdClass();

				$images = is_array($productDetail->images) ? $productDetail->images : (is_array($decoded = json_decode($productDetail->images, true)) ? $decoded : null);
				$product->image = is_array($images) ? ($images[0] ?? null) : null;
				$product->name = $productDetail->name;
				$product->expectedShippingDate = $productSupplierDetail
				? getDateRange($order->created_at, $productSupplierDetail->delivery_days)
				: null;

				/* Original Price (before discount) */
				$originalPrice = $productSupplierDetail->price ?? $orderProduct->unit_price;

				$product->priceBeforeDiscount = $originalPrice;
				$product->unitPrice = $orderProduct->unit_price;

				if (
					$productSupplierDetail &&
					$productSupplierDetail->price > $orderProduct->unit_price &&
					$productSupplierDetail->price > 0 &&
					$orderProduct->unit_price > 0
				) {
					$product->discount = (($productSupplierDetail->price - $orderProduct->unit_price) / $productSupplierDetail->price) * 100;

				} else {
					$product->discount = 0;
				}

				$product->quantity = (int) $orderProduct->quantity;
				$product->total = $orderProduct->amount;

				$products->push($product);
			}
		}

		/* Total price before discount (raw value) */
		$totalPriceWithoutDiscount = $products->sum(function ($p) {
			return (float) $p->priceBeforeDiscount * $p->quantity;
		});

		/* Total saved = original total - actual subtotal */
		$totalSaved = max(0, ($totalPriceWithoutDiscount ?? 0) - ($order->amount ?? 0));

		$subTotal = $order->amount ?? 0;
		$shippingCharge = $order->shipping_charge ?? 0;
		$taxName = config('app.website') == 'UAE' ? 'VAT' : 'SALES TAX';
		$taxPercent = $order->tax_percentage;
		$taxAmount = $order->tax_amount ?? 0;
		$total = $order->total_amount ?? 0;

		$siteUrl = config('app.website') == 'UAE' ? 'HorecaStore.ae':'Thehorecastore.com';
		$siteEmail = config('app.website') == 'UAE' ? 'hello@horecastore.ae':'sales@thehorecastore.com';

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