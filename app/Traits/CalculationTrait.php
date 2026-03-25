<?php

namespace App\Traits;

trait CalculationTrait
{
	/**
	 * Calculate totals with all charges and discounts
	 *
	 * @param object $request Request object with all necessary data
	 * @param bool $isTaxFree Customer tax free status
	 * @param object|null $existingOrder For update operations
	 * @param bool $isFrontend For frontend customer orders
	 * @return array Calculated amounts and details
	 */
	protected function calculateAmount($request, $isTaxFree = false, $existingOrder = null, $isFrontend = false, $margin = 0)
	{
		/* Collect all product supplier details in one go */
		$productDetails = [];
		foreach ($request->products as $product) {
			$fetchedDetail = productSupplierDetail($product['product_id'], $product['vendor_id']);
			if (!$fetchedDetail) {
				throw new \Exception("Product supplier not found for Product {$product['product_id']} & Vendor {$product['vendor_id']}");
			}
			$accessoryIds = $product['accessory_item_ids'] ?? [];
			$accessoryItems = getAccessoryItemIDPrice($accessoryIds);
			$accessoryPriceSum = array_sum(array_column($accessoryItems, 'price'));

			$unitPrice = $fetchedDetail->unit_price + (in_array(config('app.website'), ['UAE', 'UAE_T', 'US_T']) ? ($fetchedDetail->unit_price * ($margin / 100)) : 0);
			$productDetails[] = [
				'product_id' => $product['product_id'],
				'vendor_id' => $product['vendor_id'],
				'quantity' => $product['quantity'],
				'unit_price' => $unitPrice,
				'accessoryItems' => $accessoryItems,
				'accessory_item_charge' => $accessoryPriceSum * $product['quantity'],
				'shipping_charge' => $product['shipping_charge'],
			];
		}

		$discount = $request->discount ?? 0;
		$totalProducts = 0;
		$subtotal = 0;
		$shippingCharge = 0;

		foreach ($productDetails as $product) {
			$totalProducts += $product['quantity'];
			$subtotal += ($product['quantity'] * $product['unit_price']) + $product['accessory_item_charge'];
			$shippingCharge += $product['shipping_charge'];
		}

		/* Handle Additional Amount Price */
		if (!empty($request->additional_amount_price)) {
			$subtotal += (float) $request->additional_amount_price;
		}

		/* Handle Coupon Discount */
		$amountAfterDiscount = $subtotal - $discount;

		/* Handle Additional Discount */
		if ($request->additional_discount_option) {
			$additionalDiscountReason = $request->additional_discount_reason;
			$additionalDiscountType = $request->additional_discount_type;
			if ($additionalDiscountType == 'fixed') {
				$additionalDiscountPercentage = null;
				$additionalDiscountAmount = $request->additional_discount_amount ?? 0;
			} else if ($additionalDiscountType == 'percentage') {
				$additionalDiscountPercentage = $request->additional_discount_percentage;
				$additionalDiscountAmount = $amountAfterDiscount * $additionalDiscountPercentage / 100;
			} else {
				$additionalDiscountPercentage = null;
				$additionalDiscountAmount = 0;
			}
			$amountAfterDiscount -= $additionalDiscountAmount;
		} else {
			$additionalDiscountReason = null;
			$additionalDiscountType = null;
			$additionalDiscountPercentage = null;
			$additionalDiscountAmount = 0;
		}

		/* Handle Cheque Payment Discount - Different logic for CREATE/UPDATE/FRONTEND */
		$payWithCheque = $existingOrder ? $existingOrder->pay_with_cheque : $request->boolean('pay_with_cheque', false);
		$paymentMode = $existingOrder ? $existingOrder->payment_mode : $request->payment_mode;

		if ($payWithCheque && ($paymentMode == 'Check Payment' || !$paymentMode)) {
			/* Handle cheque_img */
			if ($request->hasFile('cheque_img')) {
				$chequeImg = uploadImageToWebpS3FromFile($request, 'cheque_img', env('STORAGE_ENV') . '/customer/orders');
			} elseif (!empty($request->cheque_img_url)) {
				$chequeImg = $request->cheque_img_url;
			} else {
				$chequeImg = $existingOrder ? $existingOrder->cheque_img : null;
			}

			/* Handle cheque_img_back */
			if ($request->hasFile('cheque_img_back')) {
				$chequeImgBack = uploadImageToWebpS3FromFile($request, 'cheque_img_back', env('STORAGE_ENV') . '/customer/orders');
			} elseif (!empty($request->cheque_img_back_url)) {
				$chequeImgBack = $request->cheque_img_back_url;
			} else {
				$chequeImgBack = $existingOrder ? $existingOrder->cheque_img_back : null;
			}

			/* Calculate discount percentage based on context */
			if ($isFrontend) {
				/* Frontend: Check if cart was created by staff */
				$cartCreatedByStaff = auth()->user()->customerCarts()->where('created_by', '>', 0)->exists();
				$chequeDiscountPercentage = $cartCreatedByStaff ? 0 : cheque_discount_percentage();
			} elseif ($existingOrder) {
				/* Backend UPDATE: Check if order was created by staff */
				$createdByStaff = $existingOrder->created_by > 0;
				$chequeDiscountPercentage = $createdByStaff ? 0 : cheque_discount_percentage();
			} else {
				/* Backend CREATE: Always 0 */
				$chequeDiscountPercentage = 0;
			}

			$chequeDiscount = $amountAfterDiscount * $chequeDiscountPercentage / 100;
			$amountAfterDiscount -= $chequeDiscount;
		} else {
			$chequeImg = null;
			$chequeImgBack = null;
			$chequeDiscountPercentage = 0;
			$chequeDiscount = 0;
		}

		/* Add extra charges */
		$liftGateCharge = $request->boolean('is_lift_gate') ? 75 : 0;
		$residentialCharge = $request->boolean('is_residential_address') ? 199 : 0;
		$insideDeliveryCharge = $request->boolean('is_inside_delivery') ? 249 : 0;
		$amountAfterDiscount += $liftGateCharge + $residentialCharge + $insideDeliveryCharge;

		/* Tax rules */
		$taxPercentage = $isTaxFree ? 0 : $request->tax_percentage;
		if (in_array(config('app.website'), ['UAE', 'UAE_T'])) {
			$taxAmount = $amountAfterDiscount * ($taxPercentage / 100);
			$shippingCharge = (($amountAfterDiscount + $taxAmount) < 500) ? 30 : 0;
		} elseif (in_array(config('app.website'), ['US', 'US_T'])) {
			$taxableAmount = $amountAfterDiscount + $shippingCharge;
			$taxAmount = $taxableAmount * ($taxPercentage / 100);
		} else {
			$taxAmount = $amountAfterDiscount * ($taxPercentage / 100);
		}

		$grandTotal = $amountAfterDiscount + $taxAmount + $shippingCharge;

		/* Calculate pending amount */
		$paidAmount = $existingOrder ? ($existingOrder->paid_amount ?? 0) : 0;
		$pendingAmount = $grandTotal - $paidAmount;

		/* Return all calculated data — all amounts rounded to 2 decimal places here */
		return [
			'product_details' => array_map(function ($product) {
				return array_merge($product, [
					'unit_price' => round($product['unit_price'], 2),
					'accessory_item_charge' => round($product['accessory_item_charge'], 2),
					'shipping_charge' => round($product['shipping_charge'], 2),
				]);
			}, $productDetails),
			'total_products' => $totalProducts,
			'subtotal' => round($subtotal, 2),
			'discount' => round($discount, 2),
			'amount_after_discount' => round($amountAfterDiscount, 2),
			'additional_discount_reason' => $additionalDiscountReason,
			'additional_discount_type' => $additionalDiscountType,
			'additional_discount_percentage' => round($additionalDiscountPercentage ?? 0, 2) ?: null,
			'additional_discount_amount' => round($additionalDiscountAmount, 2),
			'pay_with_cheque' => $payWithCheque,
			'cheque_img' => $chequeImg,
			'cheque_img_back' => $chequeImgBack,
			'cheque_discount_percentage' => round($chequeDiscountPercentage, 2),
			'cheque_discount' => round($chequeDiscount, 2),
			'lift_gate_charge' => round($liftGateCharge, 2),
			'residential_charge' => round($residentialCharge, 2),
			'inside_delivery_charge' => round($insideDeliveryCharge, 2),
			'tax_percentage' => $taxPercentage,
			'tax_amount' => round($taxAmount, 2),
			'shipping_charge' => round($shippingCharge, 2),
			'grand_total' => round($grandTotal, 2),
			'paid_amount' => round($paidAmount, 2),
			'pending_amount' => round($pendingAmount, 2),
		];
	}
}