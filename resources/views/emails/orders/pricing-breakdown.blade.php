<td>
	<table width="100%" cellspacing="0" cellpadding="0" border="0">
		<tr>
			<td valign="top" width="35%" style="padding: 0;">
				<table width="100%" cellspacing="0" cellpadding="4" border="0" style="font-family: 'Noto Sans', sans-serif; background-color:#DEF9EC; font-size:14px; line-height:20px; font-weight:bold; color:#26683A;">
					@if (floatval($totalSaved) > 0)
					<tr>
						<td style="font-weight: bold; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">
							You Saved
						</td>
						<td align="right" style="font-weight: bold; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">
							{{ $currency }} {{ number_format($totalSaved, 2, '.', ',') }}
						</td>
					</tr>
					@endif
				</table>
			</td>

			<td valign="top" width="65%" style="padding-left: 20px;">
				<table width="100%" cellspacing="0" cellpadding="4" border="0" style="font-size:14px; line-height:20px; font-family: 'Noto Sans', sans-serif;">
					@if (isset($additionalAmountName) && isset($additionalAmountPrice) && $additionalAmountPrice > 0)
					<!-- Products Subtotal (without additional amount) -->
					<tr>
						<td style="font-family: 'Noto Sans', sans-serif;">Products Subtotal</td>
						<td style="font-family: 'Noto Sans', sans-serif;" align="right">{{ $currency }} {{ number_format($subTotal - $additionalAmountPrice, 2, '.', ',') }}</td>
					</tr>

					<!-- Additional Amount as separate line -->
					<tr>
						<td style="font-family: 'Noto Sans', sans-serif;">{{ $additionalAmountName }}</td>
						<td style="font-family: 'Noto Sans', sans-serif;" align="right">{{ $currency }} {{ number_format($additionalAmountPrice, 2, '.', ',') }}</td>
					</tr>
					@endif

					<tr>
						<td style="font-family: 'Noto Sans', sans-serif; {{ isset($additionalAmountPrice) && $additionalAmountPrice > 0 ? 'font-weight: 600;' : '' }}">Subtotal</td>
						<td style="font-family: 'Noto Sans', sans-serif; {{ isset($additionalAmountPrice) && $additionalAmountPrice > 0 ? 'font-weight: 600;' : '' }}" align="right">{{ $currency }} {{ number_format($subTotal, 2, '.', ',') }}</td>
					</tr>

					@if ($discount > 0)
					<tr>
						<td style="color: #15803d; font-family: 'Noto Sans', sans-serif;">Coupon Discount</td>
						<td style="color: #15803d; font-family: 'Noto Sans', sans-serif;" align="right">- {{ $currency }} {{ number_format($discount, 2, '.', ',') }}</td>
					</tr>

					<tr>
						<td style="font-family: 'Noto Sans', sans-serif;">Subtotal After Coupon Discount</td>
						<td style="font-family: 'Noto Sans', sans-serif;" align="right">{{ $currency }} {{ number_format($subTotal - $discount, 2, '.', ',') }}</td>
					</tr>
					@endif

					@if ($additionalDiscountAmount > 0)
					<tr>
						<td style="color: #15803d; font-family: 'Noto Sans', sans-serif;">{{ $additionalDiscountReason }} @if($additionalDiscountPercentage) ({{ $additionalDiscountPercentage }}%) @endif</td>
						<td style="color: #15803d; font-family: 'Noto Sans', sans-serif;" align="right">- {{ $currency }} {{ number_format($additionalDiscountAmount, 2, '.', ',') }}</td>
					</tr>

					<tr>
						<td style="font-family: 'Noto Sans', sans-serif;">Subtotal After {{ $additionalDiscountReason }}</td>
						<td style="font-family: 'Noto Sans', sans-serif;" align="right">{{ $currency }} {{ number_format($subTotal - $discount - $additionalDiscountAmount, 2, '.', ',') }}</td>
					</tr>
					@endif

					@if ($chequeDiscount > 0)
					<tr>
						<td style="color: #15803d; font-family: 'Noto Sans', sans-serif;">Check Payment Discount ({{ $chequeDiscountPercentage }}%)</td>
						<td style="color: #15803d; font-family: 'Noto Sans', sans-serif;" align="right">- {{ $currency }} {{ number_format($chequeDiscount, 2, '.', ',') }}</td>
					</tr>

					<tr>
						<td style="font-family: 'Noto Sans', sans-serif;">Subtotal After Check Discount</td>
						<td style="font-family: 'Noto Sans', sans-serif;" align="right">{{ $currency }} {{ number_format($subTotal - $discount - $additionalDiscountAmount - $chequeDiscount, 2, '.', ',') }}</td>
					</tr>
					@endif

					@if ($liftGateCharge > 0)
					<tr>
						<td style="font-family: 'Noto Sans', sans-serif;">Lift Gate Fee</td>
						<td style="font-family: 'Noto Sans', sans-serif;" align="right">{{ $currency }} {{ number_format($liftGateCharge, 2, '.', ',') }}</td>
					</tr>
					@endif

					@if ($residentialAddressCharge > 0)
					<tr>
						<td style="font-family: 'Noto Sans', sans-serif;">Residential Delivery Fee</td>
						<td style="font-family: 'Noto Sans', sans-serif;" align="right">{{ $currency }} {{ number_format($residentialAddressCharge, 2, '.', ',') }}</td>
					</tr>
					@endif

					@if ($insideDeliveryCharge > 0)
					<tr>
						<td style="font-family: 'Noto Sans', sans-serif;">Inside Delivery Fee</td>
						<td style="font-family: 'Noto Sans', sans-serif;" align="right">{{ $currency }} {{ number_format($insideDeliveryCharge, 2, '.', ',') }}</td>
					</tr>
					@endif

					@if (in_array(config('app.website'), ['US', 'US_T']))
					<tr>
						<td style="font-family: 'Noto Sans', sans-serif;">Shipping Charge</td>
						<td style="font-family: 'Noto Sans', sans-serif;" align="right">
							{!! $shippingCharge > 0 ? $currency . ' ' . number_format($shippingCharge, 2, '.', ',') : '<span style="color: green;">Free</span>' !!}
						</td>
					</tr>
					@endif

					<tr>
						<td style="font-family: 'Noto Sans', sans-serif; font-weight: 600;">Amount Before Tax</td>
						<td style="font-family: 'Noto Sans', sans-serif; font-weight: 600;" align="right">{{ $currency }} {{ number_format($amountBeforeTax, 2, '.', ',') }}</td>
					</tr>

					<tr>
						<td style="font-family: 'Noto Sans', sans-serif;">{{ $taxName }} ({{ $taxPercent }}%)</td>
						<td style="font-family: 'Noto Sans', sans-serif;" align="right">{{ $currency }} {{ number_format($taxAmount, 2, '.', ',') }}</td>
					</tr>

					@if (!in_array(config('app.website'), ['US', 'US_T']))
					<tr>
						<td style="font-family: 'Noto Sans', sans-serif;">Shipping Charge</td>
						<td style="font-family: 'Noto Sans', sans-serif;" align="right">
							{!! $shippingCharge > 0 ? $currency . ' ' . number_format($shippingCharge, 2, '.', ',') : '<span style="color: green;">Free</span>' !!}
						</td>
					</tr>
					@endif

					<tr>
						<td colspan="2" style="border-top: 2px solid #E2E8F0;"></td>
					</tr>
					<tr style="font-weight: bold;">
						<td style="font-weight: bold; font-family: 'Noto Sans', sans-serif;">Total Amount</td>
						<td align="right" style="color: #26683A; font-weight: bold; font-family: 'Noto Sans', sans-serif;">{{ $currency }} {{ number_format($total, 2, '.', ',') }}</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</td>