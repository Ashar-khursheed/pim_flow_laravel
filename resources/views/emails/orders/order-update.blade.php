<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<title>Order Placed Successfully</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<style>
		@media only screen and (max-width: 600px) {
			.container {
				width: 100% !important;
				padding: 20px !important;
			}

			.order-button {
				display: block;
				margin: 15px 0;
			}
		}
	</style>
</head>
@php
use Illuminate\Support\Str;
@endphp
<body style="margin: 0; padding: 0; background: #ffffff; font-family: 'Noto Sans', sans-serif; color: black;">
	<!-- Preheader text: hidden but visible in email previews -->
	<span style="display: none; font-size: 1px; color: #ffffff; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
		Your order has been updated. No action required – your total remains the same.
	</span>

	<table width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f8f8f8; font-family: 'Noto Sans', sans-serif;">
		<tr>
			<td align="center">
				<table class="container" width="650" cellspacing="0" cellpadding="10" border="0" style="background:#ffffff; border:1px solid #eaeaea; font-family: 'Noto Sans', sans-serif;">
					<tr>
						<td align="left">
							<img src="{{ $logoUrl }}" alt="Logo" width="120">
						</td>
					</tr>

					<tr>
						<td>
							<p style="font-size:16px; line-height:25px; font-weight: 500; font-family: 'Noto Sans', sans-serif; margin: 0;">
								Thank You
								<strong style="color:#26683A; font-family: 'Noto Sans', sans-serif; font-size:16px; line-height:25px; margin: 0;">
									{{ $name }}
								</strong>!
							</p>
							<p style="font-size:14px; line-height:25px; font-weight: 500; font-family: 'Noto Sans', sans-serif; margin: 0;">
								Your order has been updated by our Order Fulfilment Team.
							</p>
							<p style="font-size:14px;line-height: 22px;font-family: 'Noto Sans', sans-serif;padding: 0;margin: 8px 0;">
								We made some changes to your order due to stock availability, product updates, or special pricing. Below is a clear breakdown showing the difference.
							</p>
						</td>
					</tr>

					<tr>
						<td style="padding: 10px 10px;">
							<table width="100%" cellspacing="0" cellpadding="0" border="0" style="border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden; font-family: 'Noto Sans', sans-serif;">
								<!-- Header -->
								<tr>
									<td colspan="2" style="background: #FAFAFA; padding: 15px; border-bottom: 2px solid #E2E8F0;">
										<h3 style="font-family: 'Noto Sans', sans-serif; font-size: 18px; line-height: 24px; font-weight: 600; margin: 0; color: #1a1a1a;">
											Order Update Summary
										</h3>
									</td>
								</tr>

								<!-- Original Order Total -->
								<tr>
									<td style="padding: 12px 15px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 20px; border-bottom: 1px solid #E2E8F0;">
										Original Order Total
									</td>
									<td align="right" style="padding: 12px 15px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 20px; border-bottom: 1px solid #E2E8F0;">
										{{ $currency }} {{ number_format($originalTotalAmount, 2, '.', ',') }}
									</td>
								</tr>

								<!-- New Updated Total -->
								<tr>
									<td style="padding: 12px 15px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 20px; border-bottom: 1px solid #E2E8F0;">
										New Updated Total
									</td>
									<td align="right" style="padding: 12px 15px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 20px; border-bottom: 1px solid #E2E8F0;">
										{{ $currency }} {{ number_format($total, 2, '.', ',') }}
									</td>
								</tr>

								<!-- Amount You Already Paid -->
								<tr>
									<td style="padding: 12px 15px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 20px; border-bottom: 1px solid #E2E8F0;">
										Discount Applied
									</td>
									<td align="right" style="padding: 12px 15px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 20px; border-bottom: 1px solid #E2E8F0;">
										{{ $currency }} {{ number_format($additionalDiscount, 2, '.', ',') }}
									</td>
								</tr>

								<!-- Difference (Remaining to Pay) -->
								<tr>
									<td style="padding: 12px 15px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 20px; font-weight: 600; background: #FAFAFA;">
										Price Difference
									</td>
									<td align="right" style="padding: 12px 15px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 20px; font-weight: 600; background: #FAFAFA;">
										{{ $currency }} 0
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<tr>
						<td style="padding: 10px 10px;">
							<h3 style="font-family: 'Noto Sans', sans-serif; font-size: 16px; line-height: 22px; font-weight: 600; margin: 0 0 8px; color: #1a1a1a;">
								What This Means
							</h3>
							<p style="font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 20px; margin: 0;">
								Your updated order total remains unchanged and requires no additional payment or refund.
							</p>
						</td>
					</tr>

					<tr>
						<td>
							<table cellspacing="0" cellpadding="4" style="font-family: 'Noto Sans', sans-serif; width:100%; font-size:14px; line-height:20px;">
								<tr>
									<td style="font-family: 'Noto Sans', sans-serif; vertical-align:top; width:50%; border-right:1px solid #ddd;">
										<h3 style="font-family: 'Noto Sans', sans-serif; font-size:15px; line-height:22px; font-weight: 600; margin:0 0 10px; color: #26683A; text-decoration: underline;">
											Order Summary
										</h3>

										<table>
											<tr>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; font-size: 14px; line-height:22px; color:black;">
													Order No.
												</td>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; line-height:22px; color:black; font-size: 14px;">
													:
												</td>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; line-height:22px; color:black; font-size: 14px;">
													{{ $orderNumber }}
												</td>
											</tr>
											<tr>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; font-size: 15px; line-height:22px; color:black; font-size: 14px;">
													Order Date
												</td>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; line-height:22px; color:black; font-size: 14px;">
													:
												</td>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; line-height:22px; color:black; font-size: 14px;">
													{{ $orderDate }}
												</td>
											</tr>
											<tr>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; font-size: 15px; line-height:22px; color:black; font-size: 14px;">
													Total Amount
												</td>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; line-height:22px; color:black; font-size: 14px;">
													:
												</td>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: bold; line-height:22px; color:black; font-size: 14px;">
													{{ $currency }} {{ number_format($total, 2, '.', ',') }}
												</td>
											</tr>
											<tr>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; font-size: 15px; line-height:22px; color:black; font-size: 14px;">
													Payment Status
												</td>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; line-height:22px; color:black; font-size: 14px;">
													:
												</td>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; line-height:22px; color:black; font-size: 14px;">
													Pending
													<a href="{{ $paymentUrl }}" style="color:#186737; font-family: 'Noto Sans', sans-serif; font-size:12px; line-height:18px;">[Pay Now]</a>
												</td>
											</tr>
										</table>
									</td>
									<td style="font-family: 'Noto Sans', sans-serif; vertical-align:top; padding-left:15px;">
										<h3 style="font-family: 'Noto Sans', sans-serif; font-size:15px; line-height:22px; margin:0 0 10px; color: #26683A; font-weight: 600;">
											Shipping Address
										</h3>
										<p style="font-family: 'Noto Sans', sans-serif; margin:0; font-size:14px; line-height:20px;">
											{{ $name }}
										</p>
										<p style="font-family: 'Noto Sans', sans-serif; margin:0; margin-top: 5px; font-weight: 500; color: #26683A; font-size:14px; line-height:20px;">
											{{ $address }}
										</p>
										<p style="font-family: 'Noto Sans', sans-serif; margin:0; color: #26683A; font-weight: 500; font-size:14px; line-height:20px;">
											{{ $city }}
										</p>
										<p style="font-family: 'Noto Sans', sans-serif; margin:0; color: #26683A; font-weight: 500; font-size:14px; line-height:20px;">
											{{ $country }}, {{ $zipcode }}
										</p>
										<p style="font-family: 'Noto Sans', sans-serif; margin:0; color: #26683A; font-weight: 500; font-size:14px; line-height:20px;">
											{{ $customerEmail }}
										</p>
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<tr>
						<td>
							<table class="product-table" width="100%" cellspacing="0" cellpadding="8" border="0" style="border-collapse:collapse; font-size:14px; line-height:20px; font-family: 'Noto Sans', sans-serif;">
								<tr style="background:#FAFAFA; font-weight:bold; border-bottom: 1px solid #26683A; font-family: 'Noto Sans', sans-serif; line-height:22px;">
									<td colspan="2" style="font-family: 'Noto Sans', sans-serif; line-height:22px;">
										Items Ordered
									</td>
									<td align="center" style="font-family: 'Noto Sans', sans-serif; line-height:22px;">
										Quantity
									</td>
									<td align="right" style="font-family: 'Noto Sans', sans-serif; line-height:22px;">
										Total
									</td>
								</tr>

								@foreach($products as $product)
								<tr>
									<td style="width: 12%">
										<img src="{{ $product->image }}" alt="Product" width="54" height="54" style="display: block; width: 54px; height: 54px; border: 1px solid #DFDFDF; border-radius: 4px; object-fit: cover;">
									</td>
									<td style="width: 60%">
										<strong style="font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">{{ Str::limit($product->name, 90, '...') }}</strong><br>
										<span style="color:#26683A; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">Arriving</span>
										<span style="color:#26683A; font-style:italic; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">{{ $product->expectedShippingDate }}</span><br>
										<span style="color:#BE2535; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">{{ $currency }} {{ number_format($product->priceBeforeDiscount, 2, '.', ',') }}{{ $product->discount ? ' | Save '.number_format($product->discount, 2).'%' : '' }}</span>
									</td>
									<td align="center" style="font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px; width:10%;">
										{{ $product->quantity }}
									</td>
									<td align="right" style="font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px; width:18%; ">
										{{ $currency }} {{ number_format($product->total, 2, '.', ',') }}
									</td>
								</tr>
								@endforeach
							</table>
						</td>
					</tr>

					<tr>
						<td>
							<table width="100%" cellspacing="0" cellpadding="0" border="0">
								<tr>
									<td valign="top" width="50%" style="padding: 0;">
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

									<td valign="top" width="50%" style="padding-left: 20px;">
										<table width="100%" cellspacing="0" cellpadding="4" border="0" style="font-size:14px; line-height:20px; font-family: 'Noto Sans', sans-serif;">
											@if ($liftGateCharge > 0)
											<tr>
												<td style="font-family: 'Noto Sans', sans-serif;">Lift Gate Charge</td>
												<td style="font-family: 'Noto Sans', sans-serif;" align="right">{{ $currency }} {{ number_format($liftGateCharge, 2, '.', ',') }}</td>
											</tr>
											@endif

											@if ($residentialAddressCharge > 0)
											<tr>
												<td style="font-family: 'Noto Sans', sans-serif;">Residential Address Charge</td>
												<td style="font-family: 'Noto Sans', sans-serif;" align="right">{{ $currency }} {{ number_format($residentialAddressCharge, 2, '.', ',') }}</td>
											</tr>
											@endif

											@if ($insideDeliveryCharge > 0)
											<tr>
												<td style="font-family: 'Noto Sans', sans-serif;">Inside Delivery Charge</td>
												<td style="font-family: 'Noto Sans', sans-serif;" align="right">{{ $currency }} {{ number_format($insideDeliveryCharge, 2, '.', ',') }}</td>
											</tr>
											@endif

											@if ($additionalAmountPrice > 0)
											<tr>
												<td style="font-family: 'Noto Sans', sans-serif;">{{ $additionalAmountName }}</td>
												<td style="font-family: 'Noto Sans', sans-serif;" align="right">{{ $currency }} {{ number_format($additionalAmountPrice, 2, '.', ',') }}</td>
											</tr>
											@endif

											<tr>
												<td style="font-family: 'Noto Sans', sans-serif;">Subtotal</td>
												<td style="font-family: 'Noto Sans', sans-serif;" align="right">{{ $currency }} {{ number_format($subTotal, 2, '.', ',') }}</td>
											</tr>
											<tr>
												<td style="font-family: 'Noto Sans', sans-serif;">Shipping</td>
												<td style="font-family: 'Noto Sans', sans-serif;" align="right">
													{!! $shippingCharge > 0 ? $currency . ' ' . number_format($shippingCharge, 2, '.', ',') : '<span style="color: green;">Free</span>' !!}
												</td>
											</tr>
											<tr>
												<td style="font-family: 'Noto Sans', sans-serif;">{{ $taxName }} ({{ $taxPercent }}%)</td>
												<td style="font-family: 'Noto Sans', sans-serif;" align="right">{{ $currency }} {{ number_format($taxAmount, 2, '.', ',') }}</td>
											</tr>

											@if ($discount > 0)
											<tr>
												<td style="font-family: 'Noto Sans', sans-serif;">Coupon Discount</td>
												<td style="font-family: 'Noto Sans', sans-serif;" align="right">{{ $currency }} {{ number_format($discount, 2, '.', ',') }}</td>
											</tr>
											@endif

											@if ($additionalDiscount > 0)
											<tr>
												<td style="font-family: 'Noto Sans', sans-serif;">Additional Discount</td>
												<td style="font-family: 'Noto Sans', sans-serif;" align="right">{{ $currency }} {{ number_format($additionalDiscount, 2, '.', ',') }}</td>
											</tr>
											@endif

											<tr>
												<td colspan="2" style="border-top: 2px solid #E2E8F0;"></td>
											</tr>
											<tr style="font-weight: bold; ">
												<td style="font-weight: bold;font-family: 'Noto Sans', sans-serif;">Total Amount</td>
												<td align="right" style="color: #26683A; font-weight: bold; font-family: 'Noto Sans', sans-serif;">{{ $currency }} {{ number_format($total, 2, '.', ',') }}</td>
											</tr>
										</table>
									</td>
								</tr>
							</table>

						</td>
					</tr>

					<tr>
						<td>
							<table width="100%" cellspacing="0" cellpadding="0" border="0">
								<tr>
									<td style="font-size:14px; border-top:3px solid #E2E8F0; padding-top:15px; padding-bottom:5px;  font-family: 'Noto Sans', sans-serif">
										Thank you for choosing HorecaStore - where your business gets the best, for less.
									</td>
								</tr>
								<tr>
									<td style="font-size:14px; color: #26683A; font-weight: 500; line-height:22px; font-family: 'Noto Sans', sans-serif">
										<p style="padding: 5px 0; color: #26683A; font-weight: 500; font-size: 14px; margin: 0; line-height: 20px; font-family: 'Noto Sans', sans-serif;">
											&ndash; Team HorecaStore
										</p>
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>

				<table width="650" cellspacing="0" cellpadding="0" border="0" style="padding:10px; border-top:3px solid #E2E8F0; background-color: rgba(226, 232, 240, 0.3); font-size:11px; color:#3F3F3F;">
					<tr>
						<td>
							<p style="margin: 0;font-size:12px; font-family: 'Noto Sans', sans-serif;">
								©{{ now()->year }} {{ $siteUrl }}. All rights reserved. HorecaStore, TheHorecaStore.com, and the HorecaStore.ae logo are trademarks of HorecaStore LLC or its affiliates.
							</p>
							<p style="margin: 8px 0 0; font-size:12px; font-family: 'Noto Sans', sans-serif;">
								For support or inquiries, contact us at
								<a href="mailto:{{ $siteEmail }}" style="color:#186737; font-family: 'Noto Sans', sans-serif; font-size:12px; line-height:18px;">
									{{ $siteEmail }}
								</a>.
							</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>