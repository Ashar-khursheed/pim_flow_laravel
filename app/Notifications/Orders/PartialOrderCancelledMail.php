<?php

namespace App\Notifications\Orders;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Carbon\Carbon;

class PartialOrderCancelledMail extends Notification implements ShouldQueue
{
	use Queueable;

	public $order;
	public $cancelledOrderProducts;
	public $cancellationReason;

	public function __construct($order, $cancelledOrderProducts, $cancellationReason)
	{
		$this->order = $order;
		$this->cancelledOrderProducts = $cancelledOrderProducts;
		$this->cancellationReason = $cancellationReason;
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
		$logoUrl = $backendURL . (config('app.website') === 'UAE' ? '/uae_logo.png' : '/us_logo.png');

		$name = $notifiable->name ?? 'User';
		$orderNumber = $this->order->order_number;
		$orderDate = Carbon::parse($this->order->created_at)->format('D, M d, Y');
		$currency = config('app.website') === 'UAE' ? 'AED' : 'USD';
		$paidAmount = number_format($this->order->paid_amount ?? 0, 2, '.', '');
		$paymentMethod = optional($this->order->payments()->latest()->first())->payment_mode ?? 'Cash On Delivery';

		$cancelledItems = collect();
		foreach ($this->cancelledOrderProducts as $orderProduct) {
			$productModel = $orderProduct->product;
			if ($productModel) {
				$item = new \stdClass();

				$images = is_array($productModel->images)
				? $productModel->images
				: (is_array($decoded = json_decode($productModel->images, true)) ? $decoded : null);

				$item->image = is_array($images) ? ($images[0] ?? null) : null;
				$item->name = $productModel->name;
				$item->quantity = (int) $orderProduct->quantity;
				$item->cancellationReason = $this->cancellationReason;

				$cancelledItems->push($item);
			}
		}

		$pendingItems = collect();
		foreach ($this->order->orderProducts as $orderProduct) {
			/* Skip cancelled items */
			if ($this->cancelledOrderProducts->contains('id', $orderProduct->id)) {
				continue;
			}

			/* Skip delivered or completed products */
			if (in_array($orderProduct->status, ['Delivered', 'Partially Delivered', 'Completed'])) {
				continue;
			}

			$productModel = $orderProduct->product;
			$supplier = $orderProduct->vendorProductSupplier;

			if ($productModel) {
				$item = new \stdClass();

				$images = is_array($productModel->images)
				? $productModel->images
				: (is_array($decoded = json_decode($productModel->images, true)) ? $decoded : null);

				$item->image = is_array($images) ? ($images[0] ?? null) : null;
				$item->name = $productModel->name;
				$item->quantity = (int) $orderProduct->quantity;
				$item->expectedDelivery = $supplier
				? getDateRange($this->order->created_at, $supplier->delivery_days)
				: null;

				$pendingItems->push($item);
			}
		}

		$orderListUrl = url("/registration/all-orders");
		$checkoutUrl = url("/checkout");

		$params = [
			'logoUrl' => $logoUrl,
			'name' => $name,

			'orderNumber' => $orderNumber,
			'orderDate' => $orderDate,
			'currency' => $currency,
			'paidAmount' => $paidAmount,
			'paymentMethod' => $paymentMethod,

			'cancelledItems' => $cancelledItems,
			'pendingItems' => $pendingItems,

			'orderUrl' => $orderListUrl,
			'checkoutUrl' => $checkoutUrl,
		];

		return (new MailMessage)
		->subject('Some Products from Your Order Have Been Cancelled')
		->markdown('emails.orders.partial-order-cancelled', $params);
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
