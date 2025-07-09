<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<title>Order Placed Successfully</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@300;400;500;600;700&display=swap"
	rel="stylesheet">
	<style>
		body {
			margin: 0;
			padding: 0;
			background: #ffffff;
			font-family: 'Noto Sans', sans-serif;
			color: #3F3F3F;
		}

		@media only screen and (max-width: 600px) {
			.container {
				width: 100% !important;
				padding: 20px !important;
			}

			.product-table th,
			.product-table td {
				display: block;
				width: 100%;
				text-align: left !important;
			}

			.quantity,
			.total {
				text-align: left !important;
				padding-top: 5px;
			}

			.order-button {
				display: block;
				margin: 15px 0;
			}

			.product-table th,
			.product-table td {
				display: block;
				width: 100% !important;
				text-align: left !important;
			}

			.product-table .items-header td {
				display: table-cell !important;
				width: auto !important;
			}
		}
	</style>
</head>

<body>
	<!-- Preheader text: hidden but visible in email previews -->
	<span style="display: none; font-size: 1px; color: #ffffff; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
		Thank you! We’ve received your order and will start processing it shortly.
	</span>

	<div style="font-family: 'Noto Sans', sans-serif;max-width: 650px; margin:0 auto;background:#f8f8f8;">
		<div class="container" style="font-family: 'Noto Sans', sans-serif;  background:#ffffff; padding:20px; border:1px solid #eaeaea; box-sizing:border-box;">

			<!-- Logo -->
			<div style="font-family: 'Noto Sans', sans-serif; margin-bottom:20px;">
				<img src="{{ $logoUrl }}" alt="HORECA Logo" style="font-family: 'Noto Sans', sans-serif; width:120px;">
			</div>

			<!-- Greeting -->
			<p style="font-family: 'Noto Sans', sans-serif; font-size:16px; margin:0 0 15px; font-weight: 500;">
				Thank You <strong style="font-family: 'Noto Sans', sans-serif; color:#26683A; font-weight: 500;">{{ $name }}</strong>!
			</p>
			<p style="font-family: 'Noto Sans', sans-serif; font-size:16px; margin:0 0 15px; font-weight: 500;"> Your Order Has Been Placed Successfully</p>

			<p style="font-family: 'Noto Sans', sans-serif; font-size:14px; margin:0 0 20px; line-height: 20px;">
				You’ll receive a separate confirmation email shortly with updated delivery details and next steps. If you’d like to view the status of your order or make changes, visit
			</p>

			<a href="{{ $orderUrl }}" class="order-button" style="font-family: 'Noto Sans', sans-serif; background:#26683A; color:#fff; padding:12px 24px; font-size:14px; text-decoration:none; border-radius:5px; display:inline-block;">
				Manage Your Orders
			</a>

			<!-- Order Summary & Shipping Address -->
			<table cellspacing="0" cellpadding="8" style="font-family: 'Noto Sans', sans-serif; width:100%; font-size:14px; margin-top:25px;">
				<tr>
					<td style="font-family: 'Noto Sans', sans-serif; vertical-align:top; width:50%; border-right:1px solid #ddd;">
						<h3 style="font-family: 'Noto Sans', sans-serif; font-size:15px; font-weight: 600; margin:0 0 10px; color: #26683A; text-decoration: underline;"> Order Summary</h3>
						<table>
							<tr>
								<td style="font-family: 'Noto Sans',  sans-serif; font-weight: 500; font-size: 15px; color:#3F3F3F;">Order No.</td>
								<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; color:#3F3F3F;">:</td>
								<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; color:#3F3F3F;">{{ $orderNumber }}</td>
							</tr>
							<tr>
								<td style="font-family: 'Noto Sans',  sans-serif; font-weight: 500; font-size: 15px; color:#3F3F3F;">Order Date</td>
								<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; color:#3F3F3F;">:</td>
								<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; color:#3F3F3F;">{{ $orderDate }}</td>
								<!-- DAY MMM DD YYYY please use this format -->
							</tr>
							<tr>
								<td style="font-family: 'Noto Sans',  sans-serif; font-weight: 500; font-size: 15px; color:#3F3F3F;">Amount Paid</td>
								<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; color:#3F3F3F;">:</td>
								<td style="font-family: 'Noto Sans', sans-serif; font-weight: bold; color:#3F3F3F;">{{ $currency }} {{ $paidAmount }}</td>
							</tr>
							<tr>
								<td style="font-family: 'Noto Sans',  sans-serif; font-weight: 500; font-size: 15px; color:#3F3F3F;">Payment Method</td>
								<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; color:#3F3F3F;">:</td>
								<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; color:#3F3F3F;">
									{{ $paymentMethod }}
									<!-- Mastercard <strong style="font-family: 'Noto Sans', sans-serif; color:#26683A;">(9876)</strong> -->
								</td>
							</tr>
						</table>
					</td>
					<td style="font-family: 'Noto Sans', sans-serif; vertical-align:top; padding-left:15px;">
						<h3 style="font-family: 'Noto Sans', sans-serif; font-size:15px; margin:0 0 10px; color: #26683A; font-weight: 600;">Shipping Address</h3>
						<p style="font-family: 'Noto Sans', sans-serif; margin:0;">{{ $name }}</p>
						<p style="font-family: 'Noto Sans', sans-serif; margin:0; margin-top: 5px; font-weight: 500; color: #26683A; line-height: 20px;">
							{{ $address }}
						</p>
						<p style="font-family: 'Noto Sans', sans-serif; margin:0; color: #26683A; font-weight: 500; line-height: 20px;">
							{{ $city }}
						</p>
						<p style="font-family: 'Noto Sans', sans-serif; margin:0; color: #26683A; font-weight: 500; line-height: 20px;">
							{{ $country }}, {{ $zipcode }}
						</p>
					</td>
				</tr>
			</table>

			<div></div>

			<!-- Product Table -->
			<table cellspacing="0" cellpadding="15" style="font-family: 'Noto Sans', sans-serif; margin-top: 20px; width:100%; border-collapse:collapse; font-size:14px;" class="product-table">
				<tr class="items-header" style="font-family: 'Noto Sans', sans-serif; background:#FAFAFA; padding:8px; font-weight:bold; font-size:14px;  border-bottom: 1px solid #26683A;">
					<td colspan="2">Items Ordered</td>
					<td style="font-family: 'Noto Sans', sans-serif; text-align:center;">Quantity</td>
					<td style="font-family: 'Noto Sans', sans-serif; text-align:right;">Total</td>
				</tr>

				@foreach($products as $product)
				<tr style="font-family: 'Noto Sans', sans-serif; height: 40px;">
					<td style="font-family: 'Noto Sans', sans-serif; width:54px; height: 54px;">
						<img src="{{ $product->image }}" alt="Product" style="font-family: 'Noto Sans', sans-serif;  width:54px; height:54px; border: 1px solid #DFDFDF; border-radius: 4px;">
					</td>
					<td style="font-family: 'Noto Sans', sans-serif; line-height: 16px; color:#3F3F3F;">
						<strong style="color:#3F3F3F; font-weight: 600; line-height: 25px;">{{ $product->name }}</strong><br>
						<span style="font-family: 'Noto Sans', sans-serif; color:#26683A; line-height: 20px;"> Arriving</span>
						<span style="font-family: 'Noto Sans', sans-serif; color:#26683A; line-height: 20px; font-style:italic ;">{{ $product->expectedShippingDate }}</span><br>
						<span style="font-family: 'Noto Sans', sans-serif; color:#BE2535; line-height: 20px;">{{ $currency }} {{ $product->priceBeforeDiscount }}{{ $product->discount ? ' | Save '.$product->discount.'%' : '' }}</span>
					</td>
					<td class="quantity" style="font-family: 'Noto Sans', sans-serif; text-align:center; font-weight: 500;">{{ $product->quantity }}</td>
					<td class="total" style="font-family: 'Noto Sans', sans-serif; text-align:right; font-weight: 500;">{{ $product->total }}</td>
				</tr>
				@endforeach
			</table>

			<!-- Savings Row -->
			<table cellspacing="0" cellpadding="0" style="font-family: 'Noto Sans', sans-serif; width:100%; font-size:14px; border-top:3px solid #E2DFDF;">
				<tr style="padding: 0;">
					<td>
						<div style="margin-top: 10px; font-family: 'Noto Sans', sans-serif; display:flex; flex-wrap:wrap; justify-content:space-between;">
							<div style="font-family: 'Noto Sans', sans-serif; flex:1; min-width:200px; margin-bottom:10px; padding-right: 10px;">
								<table cellspacing="0" cellpadding="8" style="font-family: 'Noto Sans', sans-serif; font-size:14px; width:100%;">
									@if($totalSaved > 0)
									<tr>
										<td style="font-family: 'Noto Sans',  sans-serif; color:#26683A;  text-align:left; font-weight:bold; background-color: #DEF9EC;">
											You Saved
										</td>
										<td style="font-family: 'Noto Sans', sans-serif; text-align:right; color:#26683A; font-weight:bold; background-color: #DEF9EC;">
											{{ $currency }} {{ $totalSaved }}
										</td>
									</tr>
									@endif
								</table>
							</div>

							<div style="font-family: 'Noto Sans', sans-serif; flex:1; min-width:200px;">
								<table cellspacing="0" cellpadding="8" style="font-family: 'Noto Sans', sans-serif; font-size:14px; width:100%;">
									<tr>
										<td style="text-align:left; font-weight: 500;">Subtotal</td>
										<td style="text-align:right;">{{ $currency }} {{ $subTotal }}</td>
									</tr>
									<tr>
										<td style="text-align:left; font-weight: 500;">Shipping</td>
										<td style="text-align:right;">{{ $currency }} {{ $shippingCharge }}</td>
									</tr>
									<tr>
										<td style="text-align:left; font-weight: 500;">VAT (5%)</td>
										<td style="text-align:right;">{{ $currency }} {{ $taxAmount }}</td>
									</tr>
									<tr style="font-weight:bold; border-top:1px solid #E2E8F0;">
										<td style="text-align:left; font-weight: 500; border-top: 3px solid #E2E8F0; padding-top: 10px;">
											Total Amount
										</td>
										<td style="text-align:right; color:#26683A; border-top: 3px solid #E2E8F0; padding-top: 10px;">
											{{ $currency }} {{ $total }}
										</td>
									</tr>
								</table>
							</div>
						</div>
					</td>
				</tr>
			</table>

			<!-- View Order Link -->
			<p style="margin:20px 0 0; font-size:14px; border-top: 3px solid #E2E8F0; padding-top: 10px; font-weight: 500;">
				You can view or update your order anytime by visiting the "Orders" section under your account profile.
			</p>

			<p style="font-size:14px; margin:20px 0 0;  font-family: 'Noto Sans', sans-serif; font-weight: 500;">
				Thank you for choosing HorecaStore - where your business gets the best, for less.
			</p>

			<p style="font-family: 'Noto Sans', sans-serif;  color: #26683A; font-weight: 500; margin: 2px 0; font-size: 14px;">
				– Team HorecaStore
			</p>
		</div>

		<div style="border-top:3px solid #E2E8F0;  padding:20px; font-size:11px; color:#3F3F3F; background-color: rgba(226, 232, 240, 0.3);">
			<p style="margin:0 0 10px;">
				©2025 HorecaStore.ae. All rights reserved. HorecaStore, TheHorecaStore.com, and the HorecaStore.ae logo are trademarks of Horeca Store LLC or its affiliates.
			</p>
			<p style="margin:0;">
				This message was sent from a notification-only address. Please do not reply directly to this email. For support or inquiries, contact us at hello@horecastore.ae
			</p>
		</div>
	</div>
</body>
</html>