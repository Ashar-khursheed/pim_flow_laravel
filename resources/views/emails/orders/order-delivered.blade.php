<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<title>Order Delivered</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<style>
		@media only screen and (max-width: 600px) {
			.container {
				width: 100% !important;
				padding: 20px !important;
			}
		}
	</style>
</head>

<body style="margin: 0; padding: 0; background: #ffffff; font-family: 'Noto Sans', sans-serif; color:black">
	<!-- Preheader text: hidden but visible in email previews -->
	<span style="display: none; font-size: 1px; color: #ffffff; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
		Your order arrived safely. Thank you for choosing HorecaStore!
	</span>

	<table width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f8f8f8; font-family: 'Noto Sans', sans-serif;">
		<tr>
			<td align="center">
				<table class="container" width="650" cellspacing="0" cellpadding="0" border="0" style="background-color: #ffffff; border: 1px solid #eaeaea; font-family: 'Noto Sans', sans-serif;">
					<tr>
						<td style="padding: 20px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 20px;">
							<img src="{{ $logoUrl }}" alt="Logo" width="120" />
						</td>
					</tr>

					<tr>
						<td style="padding: 0 20px 10px 20px; font-family: 'Noto Sans', sans-serif; font-size: 16px; line-height: 24px; color: #000;">
							<p style="font-family: 'Noto Sans', sans-serif;font-size: 16px;line-height: 24px; margin: 5px 0;padding: 0;">
								Hello <strong style="color: #26683A; font-family: 'Noto Sans', sans-serif;">{{ $name }}</strong>!
							</p>
							<p style="font-family: 'Noto Sans', sans-serif; font-size: 16px; line-height: 24px; font-family: 'Noto Sans', sans-serif;font-size: 16px;line-height: 24px;margin: 5px 0; padding: 0;">
								Your HorecaStore order <strong style="color: #26683A; font-family: 'Noto Sans', sans-serif;">#{{ $orderNumber }}</strong> containing the items below has been successfully delivered! We hope everything arrived just the way you expected.
							</p>
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
									<td style="width:12%;">
										<img src="{{ $product->image }}" alt="Product" width="54" height="54" style="border: 1px solid #DFDFDF; border-radius: 4px; width: 54px;">
									</td>
									<td  style="width:60%;">
										<strong style="font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">{{ $product->name }}</strong><br>
										<span style="color:#26683A; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">Status:</span>
										<span style="color:#26683A; font-style:italic; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">
											Delivered
										</span>
										<br>
									</td>
									<td align="center" style="font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;width:10%;">
										{{ $product->quantity }}
									</td>
									<td align="right" style="font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px; width:18%;">
										{{ $currency }} {{ number_format($product->total, 2, '.', ',') }}
									</td>
								</tr>
								@endforeach
							</table>
						</td>
					</tr>

					<tr>
						<td style="padding: 10px 20px; font-family: 'Noto Sans', sans-serif;">
							<table width="100%" cellspacing="0" cellpadding="0" border="0" style="border-top: 3px solid #E2E8F0; font-family: 'Noto Sans', sans-serif;">
								<tr>
									<td colspan="2" style="padding-top: 10px; padding-bottom: 10px; font-family: 'Noto Sans', sans-serif; font-size: 16px; font-weight: bold; color: #000; line-height: 24px;">
										What’s next?
									</td>
								</tr>
								<tr>
									<td align="center" style="font-family: 'Noto Sans', sans-serif; font-size: 14px; padding-bottom: 4px; width: 0; line-height: 20px;">
										<img src="{{ $rightPngURL }}" width="26" height="26" style="vertical-align: middle; margin-right: 5px;">
									</td>
									<td style="font-family: 'Noto Sans', sans-serif; font-size: 14px; padding-bottom: 8px; width: 95%; line-height: 20px; color: #000; font-weight: 500;">
										Need more?
										<a href="{{ $checkoutURL }}" style="text-decoration: underline; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 20px;">
											Reorder Now
										</a>
									</td>
								</tr>
								<tr>
									<td align="center" style="font-family: 'Noto Sans', sans-serif; font-size: 14px; padding-bottom: 4px; width: 0; line-height: 20px;">
										<img src="{{ $rightPngURL }}" width="26" height="26" style="vertical-align: middle; margin-right: 5px;">
									</td>
									<td style="font-family: 'Noto Sans', sans-serif; font-size: 14px; padding-bottom: 8px; width: 95%; line-height: 20px; color: #000; font-weight: 500;">
										Need a formal invoice?
										<a href="{{ $orderDetailUrl }}" style="text-decoration: underline; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 20px;">
											Download Invoice
										</a>
									</td>
								</tr>
								<tr>
									<td align="center" style="font-family: 'Noto Sans', sans-serif; font-size: 14px; padding-bottom: 4px; width: 0; line-height: 20px;">
										<img src="{{ $rightPngURL }}" width="26" height="26" style="vertical-align: middle; margin-right: 5px;">
									</td>
									<td style="font-family: 'Noto Sans', sans-serif; font-size: 14px; padding-bottom: 8px; width: 95%; line-height: 20px; color: #000; font-weight: 500;">
										Looking for contract pricing?
										<a href="mailto:{{ $siteEmail }}" style="text-decoration: underline; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 20px;">
											Request Bulk Quote
										</a>
									</td>
								</tr>
								<tr>
									<td align="center" style="font-family: 'Noto Sans', sans-serif; font-size: 14px; padding-bottom: 4px; width: 0; line-height: 20px;">
										<img src="{{ $rightPngURL }}" width="26" height="26" style="vertical-align: middle; margin-right: 5px;">
									</td>
									<td style="font-family: 'Noto Sans', sans-serif; font-size: 14px; padding-bottom: 8px; line-height: 20px; color: #000; font-weight: 500;">
										Need help with returns, warranty, or support? We’ve got your back - just reach out.
									</td>
								</tr>
							</table>
							<p style="font-family: 'Noto Sans', sans-serif; font-size: 14px;  color: #000; padding-top: 15px; border-top: 3px solid #E2E8F0; margin: 10px 0 0 0; line-height: 20px;">
								Thank you for choosing HorecaStore - we're proud to be a small part of your big journey.
							</p>
							<p style="font-family: 'Noto Sans', sans-serif; padding: 5px 0; color: #26683A;  font-size: 14px; margin: 0; line-height: 20px;">
								&ndash; Team HorecaStore
							</p>
						</td>
					</tr>

					<tr>
						<td style="padding: 20px; border-top: 2px solid #E2E8F0; background-color: rgba(226, 232, 240, 0.3); font-family: 'Noto Sans', sans-serif; font-size: 12px; color: #777; line-height: 18px;">
							<strong style="font-weight: 600; color: black; font-size: 10px; font-family: 'Noto Sans', sans-serif;">
								Order #: {{ $orderNumber }}
							</strong>
							<br><br>
							This email was sent automatically based on your order and courier tracking response. Please do not reply to this email.
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>