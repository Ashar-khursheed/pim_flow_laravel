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

<body style="margin: 0; padding: 0; background: #ffffff; font-family: 'Noto Sans', sans-serif;">
	<!-- Preheader text: hidden but visible in email previews -->
	<span style="display: none; font-size: 1px; color: #ffffff; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
		Your order arrived safely. Thank you for choosing HorecaStore!
	</span>

	<table width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f8f8f8;">
		<tr>
			<td align="center">
				<table class="container" width="650" cellspacing="0" cellpadding="0" border="0" style="background-color: #ffffff; border: 1px solid #eaeaea;">
					<tr>
						<td style="padding: 20px;">
							<img src="{{ $logoUrl }}" alt="HORECA Logo" width="120" />
						</td>
					</tr>
					<tr>
						<td style="padding: 0 20px 10px 20px; font-family: 'Noto Sans', sans-serif; font-size: 16px; color: #000;">
							<p>Hello <strong style="color: #26683A;">{{ $name }}</strong>!</p>
							<p>Your HorecaStore order <strong style="color: #26683A;">#{{ $orderNumber }}</strong> containing the items below has been successfully delivered! We hope everything arrived just the way you expected.</p>
						</td>
					</tr>
					<tr>
						<td style="padding: 0 20px 10px 20px;">
							<table width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse: collapse;">
								<tr style="background-color: #FAFAFA; border-bottom: 1px solid #26683A;">
									<th colspan="2" align="left" style="padding: 10px; font-size: 16px; font-weight: 500;">Items Ordered</th>
									<th align="right" style="padding: 10px; font-size: 16px; font-weight: 500;">Delivered Quantity</th>
								</tr>
								@foreach($products as $product)
								<tr>
									<td style="padding: 10px 0;">
										<img src="{{ $product->image }}" alt="product" width="50" style="border: 0.5px solid #DFDFDF; border-radius: 3px;   margin-right: 10px;">
									</td>
									<td style="padding: 10px 0; font-size: 14px; font-weight: 500; color: #000; width: 60%;">
										{{ $product->name }}
									</td>
									<td align="right" style="padding: 10px 0; font-size: 14px; font-weight: 500; color: #000;">
										{{ $product->quantity }}
									</td>
								</tr>
								@endforeach
							</table>
						</td>
					</tr>
					<tr>
						<td style="padding: 10px 20px; ">
							<table width="100%" cellspacing="0" cellpadding="0" border="0" style="border-top: 3px solid #E2E8F0;" >
								<tr>
									<td colspan="2" style=" padding-top: 10px; font-weight: bold; font-size: 16px; padding-bottom: 10px; font-family: 'Noto Sans', sans-serif; color: #000;"> What’s next?</td>
								</tr>
								<tr>
									<td align="center" style="font-size: 14px; padding-bottom: 4px; width: 0;">
										<img src="{{ $rightPngURL }}" width="26" height="26" style="vertical-align: middle; margin-right: 5px;">
									</td>
									<td style="font-size: 14px; padding-bottom: 8px; width: 95%;">
										Need more? <a href="{{ $checkoutURL }}" style="text-decoration: underline;">Reorder Now</a>
									</td>
								</tr>
								<tr>
									<td align="center" style="font-size: 14px; padding-bottom: 4px; width: 0;">
										<img src="{{ $rightPngURL }}" width="26" height="26" style="vertical-align: middle; margin-right: 5px;">
									</td>
									<td style="font-size: 14px; padding-bottom: 8px; width: 95%;">
										Need a formal invoice?
										<a href="{{ $orderDetailUrl }}" style="text-decoration: underline;">Download Invoice</a>
									</td>
								</tr>
								<tr>
									<td align="center" style="font-size: 14px; padding-bottom: 4px; width: 0;">
										<img src="{{ $rightPngURL }}" width="26" height="26" style="vertical-align: middle; margin-right: 5px;">
									</td>
									<td style="font-size: 14px; padding-bottom: 8px; width: 95%;">
										Looking for contract pricing?
										<a href="mailto:{{ $siteEmail }}" style="text-decoration: underline;">Request Bulk Quote</a>
									</td>


								</tr>
								<tr>
									<td align="center" style="font-size: 14px; padding-bottom: 4px; width: 0;">
										<img src="{{ $rightPngURL }}" width="26" height="26" style="vertical-align: middle; margin-right: 5px;">
									</td>
									<td style="font-size: 14px; padding-bottom: 8px; ">
										Need help with returns, warranty, or support? We’ve got your back - just reach out.
									</td>

								</tr>

							</table>
							<p style="font-weight: 500;font-size: 14px;color: #000;padding-top: 15px;border-top: 3px solid #E2E8F0;margin: 10px 0 0 0;">Thank you for choosing HorecaStore - we're proud to be a small part of your big journey.</p>
							<p style="padding: 5px 0;color: #26683A;font-weight: 500;font-size: 14px;margin: 0;">&ndash; Team HorecaStore</p>
						</td>
					</tr>
					<tr>
						<td style="padding: 20px; border-top: 2px solid #E2E8F0; background-color: rgba(226, 232, 240, 0.3); font-size: 12px; color: #777;">
							<strong style="font-weight: 600; color: #3F3F3F; font-size: 10px;">Order #: {{ $orderNumber }}</strong><br><br>
							This email was sent automatically based on your order and courier tracking response. Please do not reply to this email.
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>

</html>
