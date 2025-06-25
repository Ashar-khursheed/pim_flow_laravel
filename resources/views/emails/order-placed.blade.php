<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<title>Order Placed Successfully</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<style>
		body {
			margin: 0;
			padding: 0;
			background: #ffffff;
			font-family: 'Poppins', sans-serif;
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
	<div style=" font-family: 'Poppins', sans-serif;  width:100%; background:#f8f8f8; padding:20px 0;">
		<div class="container" style=" font-family: 'Poppins', sans-serif;  max-width:600px; margin:0 auto; background:#ffffff; padding:30px; border:1px solid #eaeaea; box-sizing:border-box;">

			<!-- Logo -->
			<div style=" font-family: 'Poppins', sans-serif;  margin-bottom:20px;">
                <img src="{{ $logoUrl }}" alt="HORECA Logo" style="width: 120px;" />
			</div>

			<!-- Greeting -->
			<p style=" font-family: 'Poppins', sans-serif;  font-size:16px; margin:0 0 15px;">
				Thank You <strong style=" font-family: 'Poppins', sans-serif;  color:#186737;">{{ $name }}</strong> ! Your Order Has Been Placed Successfully
			</p>

			<p style=" font-family: 'Poppins', sans-serif;  font-size:14px; margin:0 0 20px; line-height: 20px;">
				You’ll receive a separate confirmation email shortly with updated delivery details and next steps. If you’d like to view the status of your order or make changes, visit:
			</p>

			<a href="{{ $orderUrl }}" class="order-button" style=" font-family: 'Poppins', sans-serif;  background:#186737; color:#fff; padding:12px 24px; font-size:14px; text-decoration:none; border-radius:5px; display:inline-block;">
				Your Orders
			</a>

			<!-- Order Summary & Shipping Address -->
			<table cellspacing="0" cellpadding="8" style=" font-family: 'Poppins', sans-serif;  width:100%; font-size:14px; margin-top:25px;">
				<tr>
					<td style=" font-family: 'Poppins', sans-serif;  vertical-align:top; width:50%; border-right:1px solid #ddd;">
						<h3 style=" font-family: 'Poppins', sans-serif;  font-size:15px; margin:0 0 10px; color: #186737;">Order Summary</h3>
						<table>
							<tr>
								<td>Amount Paid</td>
								<td>:</td>
								<td><strong> {{ $paidAmount }} AED</strong></td>
							</tr>
							<tr>
								<td>Order ID</td>
								<td>:</td>
								<td><strong>{{ $orderId }}</strong></td>
							</tr>
							<tr>
								<td>Order Date</td>
								<td>:</td>
								<td><strong>{{ $orderDate }}</strong></td>
							</tr>
							<tr>
								<td>Payment Method</td>
								<td>:</td>
								<td><strong style=" font-family: 'Poppins', sans-serif;  color:#186737;">{{ $paymentMethod }}</strong></td>
							</tr>
						</table>
					</td>
					<td style=" font-family: 'Poppins', sans-serif;  vertical-align:top; padding-left:15px;">
						<h3 style=" font-family: 'Poppins', sans-serif;  font-size:15px; margin:0 0 10px; color: #186737;">Shipping Address</h3>
						<p style=" font-family: 'Poppins', sans-serif;  margin:0;">{{ $name }}</p>
						<p style=" font-family: 'Poppins', sans-serif;  margin:0; color: #186737; line-height: 20px;">{{ $address }}</p>
						<p style=" font-family: 'Poppins', sans-serif;  margin:0; color: #186737; line-height: 20px;">{{ $city }}</p>
						<p style=" font-family: 'Poppins', sans-serif;  margin:0; color: #186737; line-height: 20px;">{{ $country }}, {{ $zipcode }}</p>
					</td>
				</tr>
			</table>

			<!-- Items Ordered Header -->
			<div>
			</div>

			<!-- Product Table -->
			<table cellspacing="0" cellpadding="15" style=" font-family: 'Poppins', sans-serif;  margin-top: 20px; width:100%; border-collapse:collapse; font-size:14px;" class="product-table">
				<tr class="items-header" style=" font-family: 'Poppins', sans-serif;  background:#e5f8e7; padding:8px; font-weight:bold; font-size:14px;">
					<td colspan="2">Items Ordered</td>
					<td style=" font-family: 'Poppins', sans-serif;  text-align:center;">Quantity</td>
					<td style=" font-family: 'Poppins', sans-serif;  text-align:right;">Total</td>
				</tr>
				<tr style=" font-family: 'Poppins', sans-serif;  border-bottom:1px solid #ddd;">
					<td style=" font-family: 'Poppins', sans-serif;  width:60px;"><img src="product.png" alt="Product" style=" font-family: 'Poppins', sans-serif;  width:50px;"></td>
					<td style=" font-family: 'Poppins', sans-serif;  line-height: 20px;">
						<strong>TMS DB800-350 Double Door Chest Freezer</strong><br>
						<strong style=" font-family: 'Poppins', sans-serif;  color:#186737;"> Shipping Tomorrow, Sunday 6
						Oct</strong><br>
						<span style=" font-family: 'Poppins', sans-serif;  color:red;">AED 3,778.99 | Save 15%</span>
					</td>
					<td class="quantity" style=" font-family: 'Poppins', sans-serif;  text-align:center;">01</td>
					<td class="total" style=" font-family: 'Poppins', sans-serif;  text-align:right;">3,778.99</td>
				</tr>
			</table>

			<!-- Savings Row -->
			<table cellspacing="0" cellpadding="8" style=" font-family: 'Poppins', sans-serif;  width:100%; font-size:14px;">
				<tr>
					<td>
						<div style=" font-family: 'Poppins', sans-serif;  display:flex; flex-wrap:wrap; justify-content:space-between;">

							<div style=" font-family: 'Poppins', sans-serif;  flex:1; min-width:200px; margin-bottom:10px;">
								<table cellspacing="0" cellpadding="8" style=" font-family: 'Poppins', sans-serif;  font-size:14px; width:100%;">
									<tr>
										<td style=" font-family: 'Poppins', sans-serif;  text-align:left; font-weight:bold;">You Saved</td>
										<td style=" font-family: 'Poppins', sans-serif;  text-align:right; color:#186737; font-weight:bold;">AED 1,389.486</td>
									</tr>
								</table>
							</div>

							<div style=" font-family: 'Poppins', sans-serif;  flex:1; min-width:200px;">
								<table cellspacing="0" cellpadding="8" style=" font-family: 'Poppins', sans-serif;  font-size:14px; width:100%;">
									<tr>
										<td style=" font-family: 'Poppins', sans-serif;  text-align:left; font-weight: 500;">Subtotal</td>
										<td style=" font-family: 'Poppins', sans-serif;  text-align:right;">AED 12,345.55</td>
									</tr>
									<tr>
										<td style=" font-family: 'Poppins', sans-serif;  text-align:left; font-weight: 500;">Shipping</td>
										<td style=" font-family: 'Poppins', sans-serif;  text-align:right;">AED 5.00</td>
									</tr>
									<tr>
										<td style=" font-family: 'Poppins', sans-serif;  text-align:left; font-weight: 500;">VAT (5%)</td>
										<td style=" font-family: 'Poppins', sans-serif;  text-align:right;">AED 5.00</td>
									</tr>
									<tr style=" font-family: 'Poppins', sans-serif;  font-weight:bold; border-top:1px solid #ccc;">
										<td style=" font-family: 'Poppins', sans-serif;  text-align:left; font-weight: 500; border-top: 1px solid #ccc; padding-top: 10px;">Total Amount</td>
										<td style=" font-family: 'Poppins', sans-serif;  text-align:right; color:#186737; border-top: 1px solid #ccc; padding-top: 10px;">AED 13,389.486</td>
									</tr>
								</table>
							</div>
						</div>
					</td>
				</tr>
			</table>

			<!-- Total Breakdown -->


			<!-- View Order Link -->
			<p style="margin:20px 0 0; font-size:14px;">You can view or update your order anytime by visiting:
				<a href="#" style="font-weight:bold; text-decoration:none;">Your Orders</a>
			</p>

			<p style="font-size:14px; margin:20px 0 0;">Thank you for choosing HorecaStore — where your business gets the best, for less.</p>
			<p style="font-size:14px; color:#186737; font-weight:bold;">– Team Horeca</p>

			<!-- Footer -->
			<div style="border-top:1px solid #ccc; margin-top:30px; padding-top:15px; font-size:11px; color:#777;">
				<p style="margin:0 0 10px;">©2025 HorecaStore.ae. All rights reserved. HorecaStore and the HorecaStore logo are trademarks of Horeca Store LLC or its affiliates.</p>
				<p style="margin:0;">This message was sent from a notification-only email address that cannot receive incoming messages. Please do not reply to this email.</p>
			</div>
		</div>
	</div>
</body>
</html>