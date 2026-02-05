<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<title>Order Updated - No Payment Change</title>
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
	@if($emailType === 'pending')
	<span style="display: none; font-size: 1px; color: #ffffff; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
		Your order has been updated. Action required to complete payment.
	</span>
	@elseif($emailType === 'refund')
	<span style="display: none; font-size: 1px; color: #ffffff; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
		Your order has been updated. A refund is being processed for the difference.
	</span>
	@else
	<span style="display: none; font-size: 1px; color: #ffffff; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
		Your order has been updated. No action required – your total remains the same.
	</span>
	@endif

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

							@if($emailType === 'pending')
							<p style="font-size:14px;line-height: 22px;font-family: 'Noto Sans', sans-serif;padding: 0;margin: 8px 0;">
								Some items in your order have changed due to stock availability, updated pricing, or requested modifications. This has resulted in a small difference in your order total.
							</p>

							@elseif($emailType === 'refund')
							<p style="font-size:14px;line-height: 22px;font-family: 'Noto Sans', sans-serif;padding: 0;margin: 8px 0;">
								Some items in your order have changed due to stock availability, updated pricing, or requested modifications. This has resulted in a lower total amount than originally charged.
							</p>
							<p style="font-size:14px;line-height: 22px;font-family: 'Noto Sans', sans-serif;padding: 0;margin: 8px 0;">
								A refund will now be processed back to your original payment method.
							</p>

							@else
							<p style="font-size:14px;line-height: 22px;font-family: 'Noto Sans', sans-serif;padding: 0;margin: 8px 0;">
								We made some changes to your order due to stock availability, product updates, or special pricing. Below is a clear breakdown showing the difference.
							</p>
							@endif
						</td>
					</tr>

					<tr>
						<td style="padding: 10px 0;">
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

								<!-- Amount You Already Paid / Previously Paid -->
								<tr>
									<td style="padding: 12px 15px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 20px; border-bottom: 1px solid #E2E8F0;">
										Amount You {{ $emailType === 'refund' ? 'Previously' : 'Already' }} Paid
									</td>
									<td align="right" style="padding: 12px 15px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 20px; border-bottom: 1px solid #E2E8F0;">
										{{ $currency }} {{ number_format($paidAmount, 2, '.', ',') }}
									</td>
								</tr>

								<!-- Discount Applied -->
								@if ($additionalDiscountAmount > 0)
								<tr>
									<td style="padding: 12px 15px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 20px; border-bottom: 1px solid #E2E8F0;">
										Discount Applied
									</td>
									<td align="right" style="padding: 12px 15px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 20px; border-bottom: 1px solid #E2E8F0;">
										{{ $currency }} {{ number_format($additionalDiscountAmount ?? 0, 2, '.', ',') }}
									</td>
								</tr>
								@endif

								<tr>
									<td style="padding: 12px 15px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 20px; font-weight: 600; background: #FAFAFA;">
										@if($emailType === 'pending')
										Difference (Remaining to Pay)

										@elseif($emailType === 'refund')
										Refund to Be Processed

										@else
										Price Difference

										@endif
									</td>
									<td align="right" style="padding: 12px 15px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 20px; font-weight: 600; background: #FAFAFA;">
										{{ $currency }} {{ number_format(abs($pendingAmount), 2, '.', ',') }}
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<tr>
						<td>
							<h3 style="font-family: 'Noto Sans', sans-serif; font-size: 16px; line-height: 22px; font-weight: 600; margin: 0 0 8px; color: #1a1a1a;">
								@if($emailType === 'pending')
								Why This Change Happened

								@elseif($emailType === 'refund')
								Refund Timeline

								@else
								What This Means
								@endif
							</h3>
							<p style="font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 20px; margin: 0;">
								@if($emailType === 'pending')
								{{ $updateReason ?? 'Item price updated' }}

								@elseif($emailType === 'refund')
								Refunds typically take 3-7 business days depending on your bank.

								@else
								Your updated order total remains unchanged and requires no additional payment or refund.
								@endif
							</p>
						</td>
					</tr>

					@if ($pendingAmount > 0)
					<tr>
						<td>
							<p style="font-size:14px;line-height: 22px;font-family: 'Noto Sans', sans-serif;padding: 0;margin: 8px 0;">
								To complete processing, please use the secure payment link below to pay the remaining balance.
							</p>

							<a href="{{ $paymentUrl }}" class="order-button" style="background:#26683A; color:#fff; padding:12px 24px; margin-top: 10px; font-size:14px; line-height:20px; text-decoration:none; border-radius:5px; display:inline-block; font-family: 'Noto Sans', sans-serif;">
								Pay Remaining Balance
							</a>
						</td>
					</tr>
					@endif

					<tr>
						<td>
							<table cellspacing="0" cellpadding="4" style="font-family: 'Noto Sans', sans-serif; width:100%; font-size:14px; line-height:20px; margin-top:5px;">
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
													{{ $emailType === 'pending' ? 'Payment Status' : 'Payment Method' }}
												</td>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; line-height:22px; color:black; font-size: 14px;">
													:
												</td>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; line-height:22px; color:black; font-size: 14px;">
													@if($emailType === 'pending')
													Pending <a href="{{ $paymentUrl }}" style="color:#186737; font-family: 'Noto Sans', sans-serif; font-size:12px; line-height:18px;">[Pay Now]</a>

													@else
													{{ $paymentMethod ?? 'N/A' }}
													@endif
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
								<tr {!! !empty($product->accessories) ? 'style="border-bottom: ' . (count($product->accessories) > 0 ? 'none' : '1px solid #eaeaea') . ';"' : '' !!}>
									<td style="width: 12%;">
										<img src="{{ $product->image }}" alt="Product" width="54" height="54" style="display: block; width: 54px; height: 54px; border: 1px solid #DFDFDF; border-radius: 4px; object-fit: cover;">
									</td>
									<td style="width: 60%;">
										<strong style="font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">{{ Str::limit($product->name, 90, '...') }}</strong><br>
										<span style="color:#26683A; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">Arriving</span>
										<span style="color:#26683A; font-style:italic; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">{{ $product->expectedShippingDate }}</span><br>
										<span style="color:#BE2535; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">{{ $currency }} {{ number_format($product->priceBeforeDiscount, 2, '.', ',') }}{{ $product->discount ? ' | Save '.number_format($product->discount, 2).'%' : '' }}</span>
									</td>
									<td align="center" style="font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px; width:10%;">
										{{ $product->quantity }}
									</td>
									<td align="right" style="font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px; width:18%;">
										{{ $currency }} {{ number_format($product->total, 2, '.', ',') }}
									</td>
								</tr>

								<!-- Accessories Rows -->
								@if(!empty($product->accessories) && count($product->accessories) > 0)
									@foreach($product->accessories as $index => $accessory)
									<tr style="background:#F9FAFB; border-bottom: {{ $loop->last ? '1px solid #eaeaea' : 'none' }};">
										<td style="width: 12%;">
											<!-- Empty cell for alignment -->
										</td>
										<td style="width: 60%;">
											<span style="color:#666; font-family: 'Noto Sans', sans-serif; font-size:13px; line-height:18px;">
												<strong style="color:#26683A;">{{ $accessory['product_accessory_name'] }}:</strong> {{ $accessory['accessory_item_name'] }}
											</span>
											@if($accessory['accessory_item_price'] > 0)
											<br>
											<span style="color:#BE2535; font-family: 'Noto Sans', sans-serif; font-size:13px; line-height:18px;">
												{{ $currency }} {{ number_format($accessory['accessory_item_price'], 2, '.', ',') }} each
											</span>
											@endif
										</td>
										<td align="center" style="font-family: 'Noto Sans', sans-serif; font-size:13px; line-height:18px; width:10%;">
											{{ $product->quantity }}
										</td>
										<td align="right" style="font-family: 'Noto Sans', sans-serif; font-size:13px; line-height:18px; width:18%;">
											@if($accessory['amount'] > 0)
											{{ $currency }} {{ number_format($accessory['amount'], 2, '.', ',') }}
											@else
											<span style="color:#26683A;">Included</span>
											@endif
										</td>
									</tr>
									@endforeach
								@endif
								@endforeach
							</table>
						</td>
					</tr>

					<tr>
						@include('emails.orders.pricing-breakdown')
					</tr>

					<tr>
						<td>
							<table width="100%" cellspacing="0" cellpadding="0" border="0">
								<tr>
									<td style="font-size:14px; border-top:3px solid #E2E8F0; padding-top:15px; padding-bottom:5px;  font-family: 'Noto Sans', sans-serif">
										You can view or update your order anytime by visiting the Orders section under your account profile.
									</td>
								</tr>
								<tr>
									<td style="font-weight: 500; line-height: 24px; margin: 0; padding: 0; font-family: 'Noto Sans', sans-serif; font-size:14px;">
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