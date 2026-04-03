<?php

namespace App\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;
use App\Models\FrontEnd\Order;
use App\Models\FrontEnd\OrderProduct;
use App\Models\FrontEnd\AccessoryCharge;
use App\Models\ProductSupplier;
use App\Helpers\CurrencyConverter;

class OrderPlacedMail extends Mailable
{
	use Queueable, SerializesModels;

	public $order;

	public function __construct(Order $order)
	{
		$this->order = $order;
	}

	private function getPricingBreakdown($products, $currencyConversionRate)
	{
		$order = $this->order;
		$isUAE = in_array(config('app.website'), ['UAE', 'UAE_T']);

		/* Total price before discount */
		$totalPriceWithoutDiscount = $products->sum(fn($p) => (float) $p->priceBeforeDiscount * $p->quantity);

		/* Total saved = original total - actual subtotal */
		$totalSaved = max(0, $totalPriceWithoutDiscount - ($order->amount ?? 0));

		/* Surcharges */
		$liftGateCharge = $order->is_lift_gate ? 75 : 0;
		$residentialAddressCharge = $order->is_residential_address ? 199 : 0;
		$insideDeliveryCharge = $order->is_inside_delivery ? 249 : 0;

		/* Amounts */
		$subTotal = $order->amount ?? 0;
		$discount = $order->discount ?? 0;
		$additionalDiscountAmount = $order->additional_discount_amount ?? 0;
		$additionalDiscountReason = $order->additional_discount_reason ?? null;
		$additionalDiscountPercentage = $order->additional_discount_percentage ?? 0;
		$chequeDiscount = $order->cheque_discount ?? 0;
		$chequeDiscountPercentage = $order->cheque_discount_percentage ?? 0;

		/* Tax */
		$taxName = $isUAE ? 'VAT' : 'SALES TAX';
		$taxPercent = ($order->tax_percentage ?? 0) + 0;
		$taxAmount = $order->tax_amount ?? 0;

		/* Shipping & Total */
		$shippingCharge = $order->shipping_charge ?? 0;
		$total = $order->total_amount ?? 0;

		/* Amount before tax */
		$amountBeforeTax = $subTotal - $discount - $chequeDiscount - $additionalDiscountAmount + $liftGateCharge + $residentialAddressCharge + $insideDeliveryCharge + ($isUAE ? 0 : $shippingCharge);

		return [
			'totalSaved' => $totalSaved * $currencyConversionRate,
			'subTotal' => $subTotal * $currencyConversionRate,
			'discount' => $discount * $currencyConversionRate,
			'chequeDiscount' => $chequeDiscount * $currencyConversionRate,
			'chequeDiscountPercentage' => $chequeDiscountPercentage,
			'additionalDiscountAmount' => $additionalDiscountAmount * $currencyConversionRate,
			'additionalDiscountReason' => $additionalDiscountReason,
			'additionalDiscountPercentage' => $additionalDiscountPercentage,
			'liftGateCharge' => $liftGateCharge * $currencyConversionRate,
			'residentialAddressCharge' => $residentialAddressCharge * $currencyConversionRate,
			'insideDeliveryCharge' => $insideDeliveryCharge * $currencyConversionRate,
			'shippingCharge' => $shippingCharge * $currencyConversionRate,
			'amountBeforeTax' => $amountBeforeTax * $currencyConversionRate,
			'taxName' => $taxName,
			'taxPercent' => $taxPercent,
			'taxAmount' => $taxAmount * $currencyConversionRate,
			'total' => $total * $currencyConversionRate,
		];
	}

	public function build()
	{
		$order = $this->order;
		$isUAE = in_array(config('app.website'), ['UAE', 'UAE_T']); /* Resolved once — used throughout */

		$backendURL = config('app.backend_url');
		$logoUrl = $backendURL . '/logo.png';
		$name = $order->customer->name ?? 'User';
		$orderUrl = url('/my-order');
		$orderNumber = $order->order_number;
		$orderDate = Carbon::parse($order->created_at)->format('D, M d, Y');
		$paidAmount = $order->paid_amount ?? 0;
		$paymentMode = $order->payment_mode ? $order->payment_mode : ($order->pay_with_cheque ? 'Check' : (optional($order->payments()->latest()->first())->payment_mode ?? 'Cash On Delivery'));

		$customerAddress = $order->customerAddress;
		$address = $customerAddress->address ?? '';
		$city = $customerAddress->city ?? '';
		$country = $customerAddress->country ?? '';
		$zipcode = $customerAddress->zip_code ?? '';
		$customerEmail = $order->customer->email;

		/* Resolve source and target currency */
		$sourceCurrencySymbol = $isUAE ? 'AED' : '$';
		$sourceCurrencyTitle = $isUAE ? 'AED' : 'USD';
		$currency = $customerAddress->relatedCountry->currency->symbol ?? $sourceCurrencySymbol;
		$targetCurrencyTitle = $customerAddress->relatedCountry->currency->title ?? $sourceCurrencyTitle;
		$currencyConversionRate = CurrencyConverter::getRate($sourceCurrencyTitle, $targetCurrencyTitle) ?? 1; /* Fallback to 1 if rate unavailable */

		/* Batch-fetch vendor product suppliers — not a relation, so with() cannot be used */
		$orderProducts = $order->orderProducts;
		$vendorSuppliers = collect();

		if ($orderProducts->isNotEmpty()) {
			$vendorSuppliers = ProductSupplier::where(function ($query) use ($orderProducts) {
				foreach ($orderProducts as $orderProduct) {
					$query->orWhere(function ($q) use ($orderProduct) {
						$q->where('product_id', $orderProduct->product_id)
						->where('vendor_id', $orderProduct->vendor_id);
					});
				}
			})
			->select('id', 'product_id', 'vendor_id', 'price', 'sale_price', 'shipping_charge', 'delivery_days', 'return_policy')
			->get()
			->keyBy(fn($item) => $item->product_id . '_' . $item->vendor_id);
		}

		/* Batch-fetch accessory charges grouped by order product id */
		$orderProductIds = $orderProducts->pluck('id')->toArray();
		$accessoryChargesGrouped = AccessoryCharge::where('relation_type', OrderProduct::class)
		->whereIn('relation_id', $orderProductIds)
		->with([
			'accessoryItem.accessory',
		])
		->get()
		->groupBy('relation_id');

		/* Build products collection */
		$products = collect();

		foreach ($orderProducts as $orderProduct) {
			$productDetail = $orderProduct->product;
			if (!$productDetail) continue;

			/* Attach supplier from batch-fetched collection */
			$key = $orderProduct->product_id . '_' . $orderProduct->vendor_id;
			$productSupplierDetail = $vendorSuppliers->get($key);

			$images = is_array($productDetail->images) ? $productDetail->images : (is_array($decoded = json_decode($productDetail->images, true)) ? $decoded : null);

			/* Original price before discount */
			$originalPrice = ($productSupplierDetail->price ?? $orderProduct->unit_price) * $currencyConversionRate;

			$product = new \stdClass();
			$product->image = $images[0] ?? null;
			$product->name = $productDetail->name;
			$product->expectedShippingDate = $productSupplierDetail ? getDateRange($order->created_at, $productSupplierDetail->delivery_days) : null;
			$product->priceBeforeDiscount = $originalPrice;
			$product->unitPrice = $orderProduct->unit_price * $currencyConversionRate;
			$product->quantity = (int) $orderProduct->quantity;
			$product->total = $orderProduct->amount * $currencyConversionRate;

			/* Discount percentage — only if supplier price is higher than unit price */
			$product->discount = (
				$productSupplierDetail &&
				$productSupplierDetail->price > $orderProduct->unit_price &&
				$productSupplierDetail->price > 0 &&
				$orderProduct->unit_price > 0
			) ? (($productSupplierDetail->price - $orderProduct->unit_price) / $productSupplierDetail->price) * 100 : 0;

			/* Attach accessory charges from batch-fetched grouped collection */
			$charges = $accessoryChargesGrouped->get($orderProduct->id, collect());
			$product->accessories = $charges->map(function ($charge) use ($currencyConversionRate) {
				return [
					'id' => $charge->id,
					'accessory_item_id' => $charge->accessory_item_id,
					'accessory_item_name' => $charge->accessoryItem->name ?? null,
					'accessory_item_price' => ($charge->accessoryItem->price ?? 0) * $currencyConversionRate,
					'product_accessory_id' => $charge->accessoryItem->accessory->id ?? null,
					'product_accessory_name' => $charge->accessoryItem->accessory->name ?? null,
					'amount' => $charge->amount * $currencyConversionRate,
				];
			})->values();

			$products->push($product);
		}

		/* Pricing breakdown */
		$pricingBreakdown = $this->getPricingBreakdown($products, $currencyConversionRate);

		$shippingChargeName = $isUAE && $country == 'United Arab Emirates' ? 'Operational & Fuel Surcharge' : 'Shipping Charge';

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
			'orderUrl' => $orderUrl,
			'orderNumber' => $orderNumber,
			'orderDate' => $orderDate,
			'paidAmount' => $paidAmount * $currencyConversionRate,
			'paymentMode' => $paymentMode,
			'address' => $address,
			'city' => $city,
			'country' => $country,
			'zipcode' => $zipcode,
			'customerEmail' => $customerEmail,
			'products' => $products,
			'currency' => $currency,
			...$pricingBreakdown,
			'additionalAmountName' => $order->additional_amount_name,
			'additionalAmountPrice' => ($order->additional_amount_price ?? 0) * $currencyConversionRate,
			'shippingChargeName' => $shippingChargeName,
			'siteUrl' => $siteUrl,
			'siteEmail' => $siteEmail,
		];

		return $this->subject("Your HorecaStore Order #{$orderNumber} Has Been Successfully Placed")
		->markdown('emails.orders.order-placed')
		->with($params);
	}
}