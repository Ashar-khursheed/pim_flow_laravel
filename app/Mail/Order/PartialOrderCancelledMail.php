<?php

namespace App\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;
use App\Models\FrontEnd\OrderProduct;
use App\Models\ProductSupplier;
use App\Helpers\CurrencyConverter;

class PartialOrderCancelledMail extends Mailable
{
	use Queueable, SerializesModels;

	public $orderProduct;
	public $reason;

	public function __construct(OrderProduct $orderProduct, $reason)
	{
		$this->orderProduct = $orderProduct;
		$this->reason = $reason;
	}

	public function build()
	{
		$orderProduct = $this->orderProduct;
		$order = $orderProduct->order;
		$isUAE = in_array(config('app.website'), ['UAE', 'UAE_T']); /* Resolved once — used throughout */

		$backendURL = config('app.backend_url');
		$logoUrl = $backendURL . '/logo.png';
		$name = $order->customer->name ?? 'User'; /* Fixed: was $notifiable->name which doesn't exist */
		$orderNumber = $order->order_number;
		$orderDate = Carbon::parse($order->created_at)->format('D, M d, Y');
		$paymentMethod = optional($order->payments()->latest()->first())->payment_mode ?? 'Cash On Delivery';

		$customerAddress = $order->customerAddress;

		/* Resolve source and target currency */
		$sourceCurrencySymbol = $isUAE ? 'AED' : '$';
		$sourceCurrencyTitle = $isUAE ? 'AED' : 'USD';
		$currency = $customerAddress->relatedCountry->currency->symbol ?? $sourceCurrencySymbol;
		$targetCurrencyTitle = $customerAddress->relatedCountry->currency->title ?? $sourceCurrencyTitle;
		$currencyConversionRate = CurrencyConverter::getRate($sourceCurrencyTitle, $targetCurrencyTitle) ?? 1; /* Fallback to 1 if rate unavailable */

		/* Convert order amounts */
		$paidAmount = ($order->paid_amount ?? 0) * $currencyConversionRate;

		/* Build cancelled items collection */
		$cancelledItems = collect();
		$productModel = $orderProduct->product;

		if ($productModel) {
			$images = is_array($productModel->images) ? $productModel->images : (is_array($decoded = json_decode($productModel->images, true)) ? $decoded : null);

			$item = new \stdClass();
			$item->image = $images[0] ?? null;
			$item->name = $productModel->name;
			$item->quantity = (int) $orderProduct->quantity;
			$item->reason = $this->reason;

			$cancelledItems->push($item);
		}

		/* Filter pending order products — exclude cancelled and completed */
		$pendingOrderProducts = $order->orderProducts->filter(function ($orderItem) use ($orderProduct) {
			if ($orderItem->id == $orderProduct->id) return false;
			if (in_array($orderItem->status, ['Delivered', 'Partially Delivered', 'Completed'])) return false;
			return $orderItem->product !== null;
		})->values();

		/* Batch-fetch vendor product suppliers for pending items — not a relation, so with() cannot be used */
		$vendorSuppliers = collect();

		if ($pendingOrderProducts->isNotEmpty()) {
			$vendorSuppliers = ProductSupplier::where(function ($query) use ($pendingOrderProducts) {
				foreach ($pendingOrderProducts as $orderItem) {
					$query->orWhere(function ($q) use ($orderItem) {
						$q->where('product_id', $orderItem->product_id)
						->where('vendor_id', $orderItem->vendor_id);
					});
				}
			})
			->select('id', 'product_id', 'vendor_id', 'delivery_days')
			->get()
			->keyBy(fn($item) => $item->product_id . '_' . $item->vendor_id);
		}

		/* Build pending items collection */
		$pendingItems = collect();

		foreach ($pendingOrderProducts as $orderItem) {
			$productModel = $orderItem->product;

			/* Attach supplier from batch-fetched collection */
			$key = $orderItem->product_id . '_' . $orderItem->vendor_id;
			$supplier = $vendorSuppliers->get($key);

			$images = is_array($productModel->images) ? $productModel->images : (is_array($decoded = json_decode($productModel->images, true)) ? $decoded : null);

			$item = new \stdClass();
			$item->image = $images[0] ?? null;
			$item->name = $productModel->name;
			$item->quantity = (int) $orderItem->quantity;
			$item->expectedDelivery = $supplier ? getDateRange($order->created_at, $supplier->delivery_days) : null;

			$pendingItems->push($item);
		}

		/* Site identity based on deployment */
		$siteUrl = match (config('app.website')) {
			'UAE' => 'HorecaStore.ae',
			default => 'Thehorecastore.com',
		};

		$siteEmail = match (config('app.website')) {
			'UAE' => 'orders@horecastore.ae',
			'US_T' => 'test_us@thehorecastore.co',
			'UAE_T' => 'test_uae@thehorecastore.co',
			default => 'orders@thehorecastore.com',
		};

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
			'orderUrl' => url('/my-order'),
			'checkoutURL' => url("/view-order/{$order->id}"),
			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		return $this->subject("Some Products from Your HorecaStore Order #{$orderNumber} Have Been Cancelled")
		->markdown('emails.orders.partial-order-cancelled')
		->with($params);
	}
}