<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<title>Order Placed Successfully</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@300;400;500;600;700&display=swap"  rel="stylesheet">  <style>
		body {
			margin: 0;
			padding: 0;
			background: #ffffff;
			font-family: 'Noto Sans', sans-serif;
			color: #000000;
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

	<div style=" font-family: 'Noto Sans', sans-serif;  width:100%; background:#f8f8f8; padding:20px 0;">
		<div class="container" style=" font-family: 'Noto Sans', sans-serif;  max-width:600px; margin:0 auto; background:#ffffff; padding:30px; border:1px solid #eaeaea; box-sizing:border-box;">

			<!-- Logo -->
			<div style=" font-family: 'Noto Sans', sans-serif;  margin-bottom:20px;">
				<img src="{{ $logoUrl }}" alt="HORECA Logo" style=" font-family: 'Noto Sans', sans-serif;  width:120px;">
			</div>

			<!-- Greeting -->
			<p style=" font-family: 'Noto Sans', sans-serif;  font-size:16px; margin:0 0 15px;">
				Thank You <strong style=" font-family: 'Noto Sans', sans-serif;  color:#186737;">{{ $name }}</strong> ! Your Order Has Been Placed Successfully
			</p>

			<p style=" font-family: 'Noto Sans', sans-serif;  font-size:14px; margin:0 0 20px; line-height: 20px;">
				You’ll receive a separate confirmation email shortly with updated delivery details and next steps. If you’d like to view the status of your order or make changes, visit:
			</p>

			<a href="{{ $orderUrl }}" class="order-button" style=" font-family: 'Noto Sans', sans-serif;  background:#186737; color:#fff; padding:12px 24px; font-size:14px; text-decoration:none; border-radius:5px; display:inline-block;">
				Your Orders
		</a>

			<!-- Order Summary & Shipping Address -->
			<table cellspacing="0" cellpadding="8" style=" font-family: 'Noto Sans', sans-serif;  width:100%; font-size:14px; margin-top:25px;">
				<tr>
					<td style=" font-family: 'Noto Sans', sans-serif;  vertical-align:top; width:50%; border-right:1px solid #ddd;">
						<h3 style=" font-family: 'Noto Sans', sans-serif;  font-size:15px; margin:0 0 10px; color: #186737;">Order Summary</h3>
						<table>
							<tr>
								<td>Amount Paid</td>
								<td>:</td>
								<td><strong>{{ $paidAmount }} {{ $currency }}</strong></td>
							</tr>
							<tr>
								<td>Order ID</td>
								<td>:</td>
								<td><strong>{{ $orderNumber }}</strong></td>
							</tr>
							<tr>
								<td>Order Date</td>
								<td>:</td>
								<td><strong>{{ $orderDate }}</strong></td>
							</tr>
							<tr>
								<td>Payment Method</td>
								<td>:</td>
								<td><strong style=" font-family: 'Noto Sans', sans-serif;  color:#186737;">{{ $paymentMethod }}</strong></td>
							</tr>
						</table>
					</td>
					<td style=" font-family: 'Noto Sans', sans-serif;  vertical-align:top; padding-left:15px;">
						<h3 style=" font-family: 'Noto Sans', sans-serif;  font-size:15px; margin:0 0 10px; color: #186737;">Shipping Address</h3>
						<p style=" font-family: 'Noto Sans', sans-serif;  margin:0;">{{ $name }}</p>
						<p style=" font-family: 'Noto Sans', sans-serif;  margin:0; color: #186737; line-height: 20px;">{{ $address }}</p>
						<p style=" font-family: 'Noto Sans', sans-serif;  margin:0; color: #186737; line-height: 20px;">{{ $city }}</p>
						<p style=" font-family: 'Noto Sans', sans-serif;  margin:0; color: #186737; line-height: 20px;">{{ $country }}, {{ $zipcode }}</p>
					</td>
				</tr>
			</table>

			<!-- Items Ordered Header -->
			<div></div>

			<!-- Product Table -->
			<table cellspacing="0" cellpadding="15" style=" font-family: 'Noto Sans', sans-serif;  margin-top: 20px; width:100%; border-collapse:collapse; font-size:14px;" class="product-table">
				<tr class="items-header" style=" font-family: 'Noto Sans', sans-serif;  background:#e5f8e7; padding:8px; font-weight:bold; font-size:14px;">
					<td colspan="2">Items Ordered</td>
					<td style=" font-family: 'Noto Sans', sans-serif;  text-align:center;">Quantity</td>
					<td style=" font-family: 'Noto Sans', sans-serif;  text-align:right;">Total</td>
				</tr>
				@foreach($products as $product)
				<tr style=" font-family: 'Noto Sans', sans-serif;  border-bottom:1px solid #ddd;">
					<td style=" font-family: 'Noto Sans', sans-serif;  width:60px;"><img src="{{ $product->name }}" alt="Product" style=" font-family: 'Noto Sans', sans-serif;  width:50px;"></td>
					<td style=" font-family: 'Noto Sans', sans-serif;  line-height: 20px;">
						<strong>{{ $product->name }}</strong><br>
						<strong style=" font-family: 'Noto Sans', sans-serif;  color:#186737;"> Expected to Ship {{ $product->expectedShippingDate }}</strong><br>
						<span style=" font-family: 'Noto Sans', sans-serif;  color:red;">{{ $currency }} {{ $product->price }}{{ $product->discount ? ' | Save '.$product->discount.'%' : '' }}</span>
					</td>
					<td class="quantity" style=" font-family: 'Noto Sans', sans-serif;  text-align:center;">{{ $product->quantity }}</td>
					<td class="total" style=" font-family: 'Noto Sans', sans-serif;  text-align:right;">{{ $product->total }}</td>
				</tr>
				@endforeach
			</table>

			<!-- Savings Row -->
			<table cellspacing="0" cellpadding="8" style=" font-family: 'Noto Sans', sans-serif;  width:100%; font-size:14px;">
				<tr>
					<td>
						<div style=" font-family: 'Noto Sans', sans-serif;  display:flex; flex-wrap:wrap; justify-content:space-between;">

							<div style=" font-family: 'Noto Sans', sans-serif;  flex:1; min-width:200px; margin-bottom:10px;">
								<table cellspacing="0" cellpadding="8" style=" font-family: 'Noto Sans', sans-serif;  font-size:14px; width:100%;">
									@if($totalSaved)
									<tr>
										<td style=" font-family: 'Noto Sans', sans-serif;  text-align:left; font-weight:bold;background-color: #DEF9EC;">You Saved</td>
										<td style="background-color: #DEF9EC; font-family: 'Noto Sans', sans-serif;  text-align:right; color:#186737; font-weight:bold;">{{ $currency }} {{ $totalSaved }}</td>
									</tr>
									@endif
								</table>
							</div>

							<div style=" font-family: 'Noto Sans', sans-serif;  flex:1; min-width:200px;">
								<table cellspacing="0" cellpadding="8" style=" font-family: 'Noto Sans', sans-serif;  font-size:14px; width:100%;">
									<tr>
										<td style=" font-family: 'Noto Sans', sans-serif;  text-align:left; font-weight: 500;">Subtotal</td>
										<td style=" font-family: 'Noto Sans', sans-serif;  text-align:right;">{{ $currency }} {{ $subTotal }}</td>
									</tr>
									<tr>
										<td style=" font-family: 'Noto Sans', sans-serif;  text-align:left; font-weight: 500;">Shipping</td>
										<td style=" font-family: 'Noto Sans', sans-serif;  text-align:right;">{{ $currency }} {{ $shippingCharge }}</td>
									</tr>
									<tr>
										<td style=" font-family: 'Noto Sans', sans-serif;  text-align:left; font-weight: 500;">VAT (5%)</td>
										<td style=" font-family: 'Noto Sans', sans-serif;  text-align:right;">{{ $currency }} {{ $vat }}</td>
									</tr>
									<tr style=" font-family: 'Noto Sans', sans-serif;  font-weight:bold; border-top:1px solid #ccc;">
										<td style=" font-family: 'Noto Sans', sans-serif;  text-align:left; font-weight: 500; border-top: 1px solid #ccc; padding-top: 10px;">
											Total Amount
										</td>
										<td style=" font-family: 'Noto Sans', sans-serif;  text-align:right; color:#186737; border-top: 1px solid #ccc; padding-top: 10px;">
											{{ $currency }} {{ $total }}
										</td>
									</tr>
								</table>
							</div>

						</div>
					</td>
				</tr>
			</table>

			<!-- Total Breakdown -->


			<!-- View Order Link -->
			<p style="margin:20px 0 0; font-size:14px; border-top: 1px solid #ccc; padding-top: 10px;">
				You can view or update your order anytime by visiting:
				<a href="{{ $orderUrl }}" style="font-weight:bold; text-decoration:none;">Your Orders</a>
			</p>

			<p style="font-size:14px; margin:20px 0 0; font-family: 'Noto Sans', sans-serif; font-weight: 500; ">
				Thank you for choosing HorecaStore — where your business gets the best, for less.
			</p>
			<p style="font-size:14px; color:#186737; font-weight:bold;">– Team Horeca</p>

			<!-- Footer -->
			<div style="border-top:1px solid #ccc; margin-top:30px; padding-top:15px; font-size:11px; color:#777;">
				<p style="margin:0 0 10px;">©2025 HorecaStore.ae. All rights reserved. HorecaStore and the HorecaStore logo are trademarks of Horeca Store LLC or its affiliates.
				</p>
				<p style="margin:0;">This message was sent from a notification-only email address that cannot receive incoming messages. Please do not reply to this email.
				</p>
			</div>
		</div>
	</div>
</body>
</html>