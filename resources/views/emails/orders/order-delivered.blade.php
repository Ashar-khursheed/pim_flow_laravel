<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<title>Order Delivered Successfully</title>
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
		Your order arrived safely. Thank you for choosing HorecaStore!
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
								Hello
								<strong style="color:#26683A; font-family: 'Noto Sans', sans-serif; font-size:16px; line-height:25px; margin: 0;">
									{{ $name }}
								</strong>!
							</p>
							<p style="font-size:14px; line-height:25px; font-weight: 500; font-family: 'Noto Sans', sans-serif; margin: 0;">
								Your HorecaStore order <strong style="color:#26683A; font-family: 'Noto Sans', sans-serif;">#{{ $orderNumber }}</strong> containing the items below has been successfully delivered! We hope everything arrived just the way you expected.
							</p>
						</td>
					</tr>

					<tr>
						<td>
							<table class="product-table" width="100%" cellspacing="0" cellpadding="8" border="0" style="border-collapse:collapse; font-size:14px; line-height:20px; font-family: 'Noto Sans', sans-serif;">
								<tr style="background:#FAFAFA; font-weight:bold; border-bottom: 1px solid #26683A; font-family: 'Noto Sans', sans-serif; line-height:22px;">
									<td colspan="2" style="font-family: 'Noto Sans', sans-serif; line-height:22px;">
										Items Delivered
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
										<span style="color:#26683A; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">Status:</span>
										<span style="color:#26683A; font-style:italic; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">Delivered</span>
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
						<td style="padding-top:10px; font-family: 'Noto Sans', sans-serif;">
							<p style="font-size:15px; color:black; font-weight: 600; margin:0 0 10px; font-family: 'Noto Sans', sans-serif; line-height:22px;">
								What's next?
							</p>
							<table cellpadding="0" cellspacing="0" border="0" width="100%" style="font-family: 'Noto Sans', sans-serif; margin-top: 5px;">
								<tr>
									<td valign="top" style="padding-bottom: 8px; font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="20" height="20" style="vertical-align: middle;" />
										<span style="color:#232425; margin-left: 5px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height:20px; font-weight: 500;">Need more? <a href="{{ $checkoutURL }}" style="color:#26683A; text-decoration:underline; font-family: 'Noto Sans', sans-serif;">Reorder Now</a></span>
									</td>
								</tr>
								<tr>
									<td valign="top" style="padding-bottom: 8px; font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="20" height="20" style="vertical-align: middle;" />
										<span style="color:#232425; margin-left: 5px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height:20px; font-weight: 500;">Need a formal invoice? <a href="{{ $orderDetailUrl }}" style="color:#26683A; text-decoration:underline; font-family: 'Noto Sans', sans-serif;">Download Invoice</a></span>
									</td>
								</tr>
								<tr>
									<td valign="top" style="padding-bottom: 8px; font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="20" height="20" style="vertical-align: middle;" />
										<span style="color:#232425; margin-left: 5px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height:20px; font-weight: 500;">Looking for contract pricing? <a href="mailto:{{ $siteEmail }}" style="color:#26683A; text-decoration:underline; font-family: 'Noto Sans', sans-serif;">Request Bulk Quote</a></span>
									</td>
								</tr>
								<tr>
									<td valign="top" style="font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="20" height="20" style="vertical-align: middle;" />
										<span style="color:#232425; margin-left: 5px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height:20px; font-weight: 500;">Need help with returns, warranty, or support? We've got your back - just reach out.</span>
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
										Thank you for choosing HorecaStore - we're proud to be a small part of your big journey.
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
							<p style="margin: 0; font-weight: 600; font-size:12px; font-family: 'Noto Sans', sans-serif; line-height:18px;">
								Order #: {{ $orderNumber }}
							</p>
							<p style="margin: 8px 0 0; font-size:12px; font-family: 'Noto Sans', sans-serif; line-height:18px;">
								This email was sent automatically based on your order and courier tracking response. Please do not reply to this email.
							</p>
							<p style="margin: 8px 0 0;font-size:12px; font-family: 'Noto Sans', sans-serif;">
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