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
	 * @return array Calculated amounts and details
	 */
	protected function calculateAmount($request, $isTaxFree = false, $existingOrder = null)
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

			$productDetails[] = [
				'product_id' => $product['product_id'],
				'vendor_id' => $product['vendor_id'],
				'quantity' => $product['quantity'],
				'unit_price' => $fetchedDetail->unit_price,
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
				$additionalDiscountAmount = round($amountAfterDiscount * $additionalDiscountPercentage / 100, 2);
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

		/* Handle Cheque Payment Discount - Unified logic with ternary */
		$payWithCheque = $existingOrder ? $existingOrder->pay_with_cheque : $request->boolean('pay_with_cheque', false);
		$paymentMode = $existingOrder ? $existingOrder->payment_mode : $request->payment_mode;

		if ($payWithCheque && $paymentMode == 'Check Payment') {
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

			/* Calculate discount percentage */
			$createdByStaff = $existingOrder ? ($existingOrder->created_by > 0) : false;
			$chequeDiscountPercentage = $existingOrder ? ($createdByStaff ? 0 : cheque_discount_percentage()) : 0;
			$chequeDiscount = round($amountAfterDiscount * $chequeDiscountPercentage / 100, 2);
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
			$taxAmount = round($amountAfterDiscount * ($taxPercentage / 100), 2);
			$shippingCharge = (($amountAfterDiscount + $taxAmount) < 500) ? 30 : 0;
		} elseif (in_array(config('app.website'), ['US', 'US_T'])) {
			$taxableAmount = $amountAfterDiscount + $shippingCharge;
			$taxAmount = round($taxableAmount * ($taxPercentage / 100), 2);
		} else {
			$taxAmount = round($amountAfterDiscount * ($taxPercentage / 100), 2);
		}

		$grandTotal = $amountAfterDiscount + $taxAmount + $shippingCharge;

		/* Calculate pending amount */
		$paidAmount = $existingOrder ? ($existingOrder->paid_amount ?? 0) : 0;
		$pendingAmount = $grandTotal - $paidAmount;

		/* Return all calculated data */
		return [
			'product_details' => $productDetails,
			'total_products' => $totalProducts,
			'subtotal' => $subtotal,
			'discount' => $discount,
			'amount_after_discount' => $amountAfterDiscount,
			'additional_discount_reason' => $additionalDiscountReason,
			'additional_discount_type' => $additionalDiscountType,
			'additional_discount_percentage' => $additionalDiscountPercentage,
			'additional_discount_amount' => $additionalDiscountAmount,
			'pay_with_cheque' => $payWithCheque,
			'cheque_img' => $chequeImg,
			'cheque_img_back' => $chequeImgBack,
			'cheque_discount_percentage' => $chequeDiscountPercentage,
			'cheque_discount' => $chequeDiscount,
			'lift_gate_charge' => $liftGateCharge,
			'residential_charge' => $residentialCharge,
			'inside_delivery_charge' => $insideDeliveryCharge,
			'tax_percentage' => $taxPercentage,
			'tax_amount' => $taxAmount,
			'shipping_charge' => $shippingCharge,
			'grand_total' => $grandTotal,
			'paid_amount' => $paidAmount,
			'pending_amount' => $pendingAmount,
		];
	}
}