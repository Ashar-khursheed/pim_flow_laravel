<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<title>Order Update</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@300;400;500;600;700&display=swap"
	rel="stylesheet">
	<style>
		@media only screen and (max-width: 600px) {
			.container {
				width: 100% !important;
				padding: 20px !important;
			}

			.product-table td {
				display: block;
				width: 100%;
				text-align: left !important;
			}

			.quantity {
				text-align: left !important;
				padding-top: 5px;
			}

			.order-link {
				display: block;
				margin-top: 15px;
			}
		}
	</style>
</head>

<body style="margin:0; padding:0; background:#ffffff; font-family: 'Noto Sans', sans-serif; color:#232425;">

	<div style=" max-width:600px; margin:0 auto;   background:#f8f8f8;">
		<div class="container" style=" width:100%;  background:#ffffff; padding:20px; border:1px solid #eaeaea; box-sizing:border-box;">

			<!-- Logo -->
			<div style="margin-bottom:20px;">
				<img src="{{ $logoUrl }}" alt="HORECA Logo" style="width:120px;">
			</div>

			<!-- Greeting -->
			<p style="font-size:16px;">Hello <strong style="color:#26683A;">{{ $name }}</strong>!</p>

			<p style="font-size:14px;">
				You've successfully cancelled the following item(s) from your order.<br/>
				We’ll notify you once the remaining items are shipped.
			</p>

			<!-- Order Summary -->
			<h3 style="font-size:16px; margin:0px; color:#26683A; font-weight: 500;">Order Summary</h3>
			<table cellspacing="0" cellpadding="5" style="width:100%; font-size:14px; color:#3F3F3F;">
				<tr>
					<td style="font-weight:500; font-size: 14px;">Order Number</td>
					<td>:</td>
					<td style="font-weight:500; font-size: 12px;">{{ $orderNumber }}</td>
				</tr>
				<tr>
					<td style="font-weight:500; font-size: 14px;">Order Date</td>
					<td>:</td>
					<td style="font-weight:500; font-size: 12px;">{{ $orderDate }}</td>
				</tr>
				<tr>
					<td style="font-weight:500; font-size: 14px;">Amount Paid</td>
					<td>:</td>
					<td style="font-weight:500; font-size: 12px;">{{ $currency }} {{ $paidAmount }}</td>
				</tr>
				<tr>
					<td style="font-weight:500; font-size: 14px;">Payment Method</td>
					<td>:</td>
					<td style="font-weight:500; color:#3F3F3F; font-size: 12px;"> {{ $paymentMethod }}{{-- Mastercard <span style="color:#26683A;">(9876)</span> --}} </td>
				</tr>
			</table>

			<!-- Cancelled Items Section -->
			<div style="border-bottom: 1px solid #26683A; display: flex; align-items: center; justify-content: space-between; margin-top:15px; background-color:#FAFAFA; padding:5px 8px; font-weight:bold; font-size:14px; color:#232425;">
				<p style="padding: 0; margin: 0; font-size: 15px; font-weight: 500; padding: 5px 0; color: #BE2535;">
					Cancelled Items
				</p>
				<p style="padding: 0; margin: 0; font-size: 15px; font-weight: 500; padding: 5px 0 ;color: #BE2535; ">
					Quantity
				</p>
			</div>
			<table cellspacing="0" cellpadding="2" style="width:100%; border-collapse:collapse; font-size:14px; margin-top:0;" class="product-table">
				@foreach($cancelledItems as $product)
				<tr style=" margin: 5px 0;">
					<td style="width:60px;"><img src="{{ $product->image }}" alt="Product" style="width:60px;  border-radius: 3px;"></td>
					<td>
						<strong>{{ $product->name }}</strong><br>
						<span>Cancellation Reason: </span>
						<span style=" font-style: italic;">
							{{ $product->cancellationReason }}
						</span>
					</td>
					<td class="quantity" style="text-align:right; font-weight: 500;">{{ $product->quantity }}</td>
				</tr>
				@endforeach
			</table>

			<!-- Remaining Items Section -->
			<div style="border-bottom: 1px solid #26683A; display: flex; align-items: center; justify-content: space-between; margin-top:15px; background:#e5f8e7; padding:5px 8px; font-weight:bold; font-size:14px; color:#232425;">
				<p style="padding: 0; margin: 0; font-size: 15px; font-weight: 500; padding: 5px 0;">
					Items Still on Their Way
				</p>
				<p style="padding: 0; margin: 0; font-size: 15px; font-weight: 500; padding: 5px 0;">
					Quantity
				</p>
			</div>
			<table cellspacing="0" cellpadding="2" style="width:100%; border-collapse:collapse; font-size:14px; margin-top:0;" class="product-table">
				@foreach($pendingItems as $product)
				<tr>
					<td style="width:60px;"><img src="{{ $product->image }}" alt="Product" style="width:60px;  border-radius: 3px;"></td>
					<td>
						<strong>{{ $product->name }}</strong><br>
						<span>Arriving </span><span style="color:#26683A; font-style: italic;">
							{{ $product->expectedDelivery }}
						</span>
					</td>
					<td class="quantity" style="text-align:right; font-weight: 500;">{{ $product->quantity }}</td>
				</tr>
				@endforeach
			</table>

			<p style="margin:20px 0 0; font-size:14px; border-top:3px solid #E2DFDF; padding: 15px 0 0 0;">
				You can view or update your order anytime by visiting the
				<a href="{{ $orderUrl }}" class="order-link" style="font-weight: 500; text-decoration:underline; ">Orders</a>
				section under your account profile.
			</p>
			<p style="font-size:14px; line-height: 24px;  margin:20px 0 0; padding-top: 10px; border-top: 3px solid #E2DFDF;">
				We understand things change - and that’s okay.
			</p>
			<p style="font-size:14px; line-height: 20px;  margin:2px 0 0; padding-top: 2px;">
				Whether now or later, we’ll be right here with great prices, honest service, and the support your business
				deserves.<br />
			</p>
			<p style="font-size:14px; line-height: 24px;  margin:5px 0 0; padding-top: 5px;">
				Changed your mind?
				<a href="{{ $checkoutUrl }}" class="order-link" style=" text-decoration:underline;  font-weight: 500; line-height: 22px;"> Click here to reorder</a>
				- we’d love to serve you again.
			</p>
			<p style="font-family: 'Noto Sans', sans-serif;  color: #26683A; font-weight: 500; margin: 2px 0; font-size: 14px;">
				– Team HorecaStore
			</p>
		</div>

		<div class="footer-note" style=" font-family: 'Noto Sans', sans-serif; border-top:2px solid #E2E8F0; margin-top:0px; padding:20px; font-size:12px;background-color: rgba(226, 232, 240, 0.3);color:#3F3F3F;">
			<p style="margin:0; font-family: 'Noto Sans', sans-serif; ">
				©2025 HorecaStore.ae. All rights reserved. HorecaStore, TheHorecaStore.com, and the HorecaStore.ae logo are trademarks of Horeca Store LLC or its affiliates.
			</p>
			<p style="margin:8px 0 0; font-family: 'Noto Sans', sans-serif; ">
				This message was sent from a notification-only email address that cannot receive incoming messages. Please do not reply to this email.
			</p>
		</div>
	</div>

</body>

</html>