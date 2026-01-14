<?php

namespace App\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

use App\Models\FrontEnd\Order;
use App\Models\FrontEnd\OrderProduct;
use App\Models\FrontEnd\AccessoryCharge;

class OrderUpdateMail extends Mailable
{
	use Queueable, SerializesModels;

	public $order;
	public $originalTotalAmount;
	public $updateReason;

	/**
	 * Create a new message instance.
	 */
	public function __construct(Order $order, $originalTotalAmount, $updateReason)
	{
		$this->order = $order;
		$this->originalTotalAmount = $originalTotalAmount;
		$this->updateReason = $updateReason;
	}

	/**
	 * Get pricing breakdown variables
	 */
	private function getPricingBreakdown($products)
	{
		$order = $this->order;

		/* Total price before discount (raw value) */
		$totalPriceWithoutDiscount = $products->sum(function ($p) {
			return (float) $p->priceBeforeDiscount * $p->quantity;
		});

		/* Total saved = original total - actual subtotal */
		$totalSaved = max(0, ($totalPriceWithoutDiscount ?? 0) - ($order->amount ?? 0));

		/* Charges */
		$liftGateCharge = $order->is_lift_gate ? 75 : 0;
		$residentialAddressCharge = $order->is_residential_address ? 199 : 0;
		$insideDeliveryCharge = $order->is_inside_delivery ? 249 : 0;

		/* Discounts & Amounts */
		$subTotal = $order->amount ?? 0;
		$discount = $order->discount ?? 0;
		$additionalDiscountAmount = $order->additional_discount_amount ?? 0;
		$additionalDiscountPercentage = $order->additional_discount_percentage ?? 0;
		$chequeDiscount = $order->cheque_discount ?? 0;
		$chequeDiscountPercentage = $order->cheque_discount_percentage ?? 0;

		/* Tax */
		$taxName = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 'VAT' : 'SALES TAX';
		$taxPercent = ($order->tax_percentage ?? 0) + 0;
		$taxAmount = $order->tax_amount ?? 0;

		/* Shipping & Total */
		$shippingCharge = $order->shipping_charge ?? 0;
		$total = $order->total_amount ?? 0;

		/* Amount Before Tax */
		$amountBeforeTax = $subTotal - $discount - $chequeDiscount - $additionalDiscountAmount + $liftGateCharge + $residentialAddressCharge + $insideDeliveryCharge + (in_array(config('app.website'), ['UAE', 'UAE_T']) ? 0 : $shippingCharge);

		/* Currency */
		$currency = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 'AED' : '$';

		return [
			'totalSaved' => $totalSaved,
			'currency' => $currency,
			'subTotal' => $subTotal,
			'discount' => $discount,
			'chequeDiscount' => $chequeDiscount,
			'chequeDiscountPercentage' => $chequeDiscountPercentage,
			'additionalDiscountAmount' => $additionalDiscountAmount,
			'additionalDiscountPercentage' => $additionalDiscountPercentage,
			'liftGateCharge' => $liftGateCharge,
			'residentialAddressCharge' => $residentialAddressCharge,
			'insideDeliveryCharge' => $insideDeliveryCharge,
			'shippingCharge' => $shippingCharge,
			'amountBeforeTax' => $amountBeforeTax,
			'taxName' => $taxName,
			'taxPercent' => $taxPercent,
			'taxAmount' => $taxAmount,
			'total' => $total,
		];
	}

	public function build()
	{
		$order = $this->order;

		$backendURL = config('app.backend_url');
		$logoUrl = $backendURL . '/logo.png';
		$name = $order->customer->name ?? 'User';

		$originalTotalAmount = $this->originalTotalAmount;
		$paidAmount = $order->paid_amount ?? 0;
		$pendingAmount = $order->pending_amount ?? 0;
		$updateReason = $this->updateReason;

		$paymentUrl = $order->payment_link ?? url("/");
		$orderNumber = $order->order_number;
		$orderDate = Carbon::parse($order->created_at)->format('D, M d, Y');

		$customerAddress = $order->customerAddress;
		$address = $customerAddress->address ?? '';
		$city = $customerAddress->city ?? '';
		$country = $customerAddress->country ?? '';
		$zipcode = $customerAddress->zip_code ?? '';
		$customerEmail = $order->customer->email;

		$products = collect();

		foreach ($order->orderProducts as $orderProduct) {
			$productSupplierDetail = $orderProduct->vendorProductSupplier;
			$productDetail = $orderProduct->product;

			if ($productDetail) {
				$product = new \stdClass();

				$images = is_array($productDetail->images)
				? $productDetail->images
				: (is_array($decoded = json_decode($productDetail->images, true)) ? $decoded : null);
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

				$product->accessories = [];

				$accessoryCharges = AccessoryCharge::where('relation_type', OrderProduct::class)
				->where('relation_id', $orderProduct->id)
				->get();
				if ($accessoryCharges->isNotEmpty()) {
					$product->accessories = $accessoryCharges->map(function ($charge) {
						return [
							'id' => $charge->id,
							'accessory_item_id' => $charge->accessory_item_id,
							'accessory_item_name' => $charge->accessoryItem->name ?? null,
							'accessory_item_price' => $charge->accessoryItem->price ?? null,
							'product_accessory_id' => $charge->accessoryItem->accessory->id ?? null,
							'product_accessory_name' => $charge->accessoryItem->accessory->name ?? null,
							'amount' => $charge->amount,
						];
					});
				}

				$products->push($product);
			}
		}

		/* Get pricing breakdown variables */
		$pricingBreakdown = $this->getPricingBreakdown($products);

		/* Additional charges (if exists) */
		$additionalAmountName = $order->additional_amount_name;
		$additionalAmountPrice = $order->additional_amount_price;

		$siteUrl = match (config('app.website')) {
			'US'  => 'Thehorecastore.com',
			'UAE'  => 'HorecaStore.ae',
			'TEST' => 'Thehorecastore.com',
			default => 'Thehorecastore.com',
		};

		$siteEmail = match (config('app.website')) {
			'US'  => 'orders@thehorecastore.com',
			'UAE'  => 'orders@horecastore.ae',
			'US_T' => 'test_us@thehorecastore.co',
			'UAE_T' => 'test_uae@thehorecastore.co',
			default => 'test@thehorecastore.co',
		};

		$params = [
			'logoUrl' => $logoUrl,
			'name' => $name,

			'originalTotalAmount' => $originalTotalAmount,
			'paidAmount' => $paidAmount,
			'pendingAmount' => $pendingAmount,
			'updateReason' => $updateReason,

			'paymentUrl' => $paymentUrl,
			'orderNumber' => $orderNumber,
			'orderDate' => $orderDate,

			'address' => $address,
			'city' => $city,
			'country' => $country,
			'zipcode' => $zipcode,
			'customerEmail' => $customerEmail,

			'products' => $products,

			'additionalAmountName' => $additionalAmountName,
			'additionalAmountPrice' => $additionalAmountPrice,

			/* Merge pricing breakdown variables */
			...$pricingBreakdown,

			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		/* Determine email subject and template based on pending amount */
		if ($pendingAmount > 0) {
			/* Customer needs to pay remaining amount */
			$emailType = 'pending';
			$subject = "Update on Your HorecaStore Order #{$orderNumber} – Action Required";
		} elseif ($pendingAmount < 0) {
			/* Customer will receive a refund */
			$emailType = 'refund';
			$subject = "Update on Your HorecaStore Order #{$orderNumber} – Refund Processing";
		// } elseif ($pendingAmount == 0 && $pricingBreakdown['additionalDiscountAmount'] > 0) {
		// 	/* Discount applied, no payment needed */
		// 	$subject = "Update on Your HorecaStore Order #{$orderNumber} – No Action Required";
		} else {
			/* Default: standard order update */
			$emailType = 'default';
			$subject = "Update on Your HorecaStore Order #{$orderNumber} – No Action Required";
		}
		$params['emailType'] = $emailType;

		return $this->subject($subject)
		->markdown("emails.orders.order-update")
		->with($params);
	}
}