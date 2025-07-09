<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<title>Order Delivered</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@300;400;500;600;700&display=swap"
	rel="stylesheet">
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

	<div style="font-family: 'Noto Sans', sans-serif; max-width: 600px;  margin: 0 auto;  padding: 0px; box-sizing: border-box; background-color: #f8f8f8;">
		<div class="container" style="font-family: 'Noto Sans', sans-serif;   background-color: #ffffff; padding: 20px; border: 1px solid #eaeaea; box-sizing: border-box;">

			<!-- Logo -->
			<div style="font-family: 'Noto Sans', sans-serif;  margin-bottom: 20px;">
				<img src="{{ $logoUrl }}" alt="HORECA Logo" style="font-family: 'Noto Sans', sans-serif;  width: 120px;" />
			</div>

			<!-- Greeting -->
			<p style="font-family: 'Noto Sans', sans-serif;  font-size: 16px; color: #000000;">
				Hello <strong style="font-family: 'Noto Sans', sans-serif;  color: #26683A;">{{ $name }}</strong>!
			</p>
			<p style="font-family: 'Noto Sans', sans-serif;  font-size: 15px; color: #000000;">
				Your HorecaStore order <strong style="font-family: 'Noto Sans', sans-serif;  color: #26683A;">#{{ $orderNumber }}</strong> containing the items below has been successfully delivered! We hope everything arrived just the way you expected.
			</p>

			<!-- Items Table -->
			<table class="items-table" style="font-family: 'Noto Sans', sans-serif;  width: 100%; border-collapse: collapse; margin-top: 20px;">
				<thead>
					<tr>
						<th colspan="2" style="line-height: 25px; font-size: 16px; font-family: 'Noto Sans', sans-serif;  text-align: left;  width: 64%; padding: 10px; font-weight: 500; border-bottom: 1px solid #26683A; background-color: #FAFAFA;">
							Items Ordered
						</th>
						<th style="line-height: 25px; font-size: 16px; font-family: 'Noto Sans', sans-serif;  text-align: right; padding: 10px; font-weight: 500; border-bottom: 1px solid #26683A; background-color: #FAFAFA;">
							Delivered Quantity
						</th>
					</tr>
				</thead>
				<tbody>
					@foreach($products as $product)
					<tr style="font-family: 'Noto Sans', sans-serif;  ">
						<td style="font-family: 'Noto Sans', sans-serif;  padding: 10px 0; vertical-align: middle;">
							<img src="{{ $product->image }}" alt="product" width="60" style="width:50px; border: 0.5px solid #DFDFDF; border-radius: 3px;margin-right: 20px;" />
						</td>
						<td style="font-family: 'Noto Sans', sans-serif;  padding: 10px 0; ">
							<span style="font-family: 'Noto Sans', sans-serif;  font-size: 14px; color: #000; font-weight: 500;">
								{{ $product->name }}
							</span>
						</td>
						<td style="font-family: 'Noto Sans', sans-serif;  text-align: right; vertical-align: middle; font-size: 14px; font-weight: 500; color: #000;">
							{{ $product->quantity }}
						</td>
					</tr>
					@endforeach
				</tbody>
			</table>

			<!-- Next Steps -->
			<div style="font-family: 'Noto Sans', sans-serif;  margin-top: 15px; border-top: 3px solid #E2E8F0;">
				<p style="font-family: 'Noto Sans', sans-serif;  font-weight: bold; font-size: 16px; margin-bottom: 5px;">
					What’s next?
				</p>
				<p style="font-family: 'Noto Sans', sans-serif;  font-size: 14px; color: #000;  display: flex; align-items: center; margin: 2.5px 0 ; ">
					<img src="{{ $rightPngURL }}" alt="right" width="26px" height="26px" style="margin-right: 5px;">
					Need more?
					<a href="{{ $checkoutURL }}" style="font-family: 'Noto Sans', sans-serif;  text-decoration: underline; margin-left: 5px;">
						Reorder Now
					</a>
				</p>

				<p style="font-family: 'Noto Sans', sans-serif;  font-size: 14px; color: #000;   display: flex; align-items: center;  margin: 2.5px 0;">
					<img src="{{ $rightPngURL }}" alt="right" width="26px" height="26px" style="margin-right: 5px;">
					Need a formal invoice?
					<a href="{{ $orderDetailUrl }}" style="font-family: 'Noto Sans', sans-serif;  text-decoration: underline; margin-left: 5px;">
						Download Invoice
					</a>
				</p>

				<p style="font-family: 'Noto Sans', sans-serif;  font-size: 14px; color: #000;  display: flex; align-items: center; margin: 3.5px 0; ">
					<img src="{{ $rightPngURL }}" alt="right" width="26px" height="26px" style="margin-right: 5px;">
					Looking for contract pricing?
					<a href="mailto:{{ $siteEmail }}" style="font-family: 'Noto Sans', sans-serif;  text-decoration: underline; margin-left: 5px;">
						Request Bulk Quote
					</a>
				</p>

				<p style="font-family: 'Noto Sans', sans-serif;  font-size: 14px; color: #000;  display: flex; align-items: center; margin: 3.5px 0; ">
					<img src="{{ $rightPngURL }}" alt="right" width="26px" height="26px" style="margin-right: 5px;">
					<span> Need help with returns, warranty, or support? We’ve got your back - just reach out.</span>
				</p>
			</div>

			<p style="font-weight: 500; border-top: 3px solid #E2E8F0;  padding-top: 20px; font-family: 'Noto Sans', sans-serif;  margin-top: 15px; font-size: 14px; color: #000;">
				Thank you for choosing HorecaStore - we're proud to be a small part of your big journey.
			</p>
			<p style="font-family: 'Noto Sans', sans-serif;  color: #26683A; font-weight: 500; margin: 2px 0; font-size: 14px;">
				– Team HorecaStore
			</p>
		</div>

		<div style="font-family: 'Noto Sans', sans-serif;  border-top: 2px solid #E2E8F0; padding: 20px; font-size: 12px; color: #777;  background-color: rgba(226, 232, 240, 0.3);">
			<strong style="font-weight: 600; color:#3F3F3F; font-size: 10px; "> Order #: {{ $orderNumber }}</strong><br />
			<br />
			This email was sent automatically based on your order and courier tracking response. Please do not reply to this email.
		</div>
	</div>
</body>
</html>