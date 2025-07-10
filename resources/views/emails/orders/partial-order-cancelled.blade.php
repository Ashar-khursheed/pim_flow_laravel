<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<title>Order Update</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
	<table width="100%" border="0" cellspacing="0" cellpadding="0" style="background:#f8f8f8;">
		<tr>
			<td align="center">
				<table width="600" border="0" cellspacing="0" cellpadding="0" class="container" style="background:#ffffff; border:1px solid #eaeaea;">
					<tr>
						<td style="padding:20px;">
							<table width="100%" border="0" cellspacing="0" cellpadding="0">
								<tr>
									<td style="padding-bottom:20px;">
										<img src="{{ $logoUrl }}" alt="HORECA Logo" style="width:120px;">
									</td>
								</tr>
								<tr>
									<td style="font-size:16px; padding-bottom:10px;">Hello <strong style="color:#26683A;">{{ $name }}</strong>!</td>
								</tr>
								<tr>
									<td style="font-size:14px; padding-bottom:15px;">You've successfully cancelled the following item(s) from your order.<br/>We’ll notify you once the remaining items are shipped.</td>
								</tr>
							</table>

							<!-- Order Summary -->
							<table width="100%" border="0" cellspacing="0" cellpadding="5" style="font-size:14px; color:#3F3F3F;">
								<tr>
									<td style="font-weight:500; font-size:14px;">Order Number</td>
									<td>:</td>
									<td style="font-weight:500; font-size:12px;">{{ $orderNumber }}</td>
								</tr>
								<tr>
									<td style="font-weight:500; font-size:14px;">Order Date</td>
									<td>:</td>
									<td style="font-weight:500; font-size:12px;">{{ $orderDate }}</td>
								</tr>
								<tr>
									<td style="font-weight:500; font-size:14px;">Amount Paid</td>
									<td>:</td>
									<td style="font-weight:500; font-size:12px;">{{ $currency }} {{ $paidAmount }}</td>
								</tr>
								<tr>
									<td style="font-weight:500; font-size:14px;">Payment Method</td>
									<td>:</td>
									<td style="font-weight:500; font-size:12px;">{{ $paymentMethod }}{{-- Mastercard <span style="color:#26683A;">(9876)</span> --}}</td>
								</tr>
							</table>

							<!-- Cancelled Items Section -->
							<table width="100%" border="0" cellspacing="0" cellpadding="8" style="margin-top:15px; background-color:#FAFAFA; border-bottom:1px solid #26683A;">
								<tr>
									<td style="font-weight:500; font-size:15px; color:#BE2535;">Cancelled Items</td>
									<td style="font-weight:500; font-size:15px; color:#BE2535; text-align:right;">Quantity</td>
								</tr>
							</table>
							<table width="100%" border="0" cellspacing="0" cellpadding="5" class="product-table">
								@foreach($cancelledItems as $product)
								<tr>
									<td style="width:60px;"><img src="{{ $product->image }}" alt="Product" style="width:60px; border-radius:3px;"></td>
									<td>
										<strong>{{ $product->name }}</strong><br>
										<span>Cancellation Reason:</span>
										<span style="font-style:italic;">{{ $product->cancellationReason }}</span>
									</td>
									<td class="quantity" style="text-align:right; font-weight:500;">{{ $product->quantity }}</td>
								</tr>
								@endforeach
							</table>

							<!-- Remaining Items Section -->
							<table width="100%" border="0" cellspacing="0" cellpadding="8" style="margin-top:15px; background-color:#e5f8e7; border-bottom:1px solid #26683A;">
								<tr>
									<td style="font-weight:500; font-size:15px;">Items Still on Their Way</td>
									<td style="font-weight:500; font-size:15px; text-align:right;">Quantity</td>
								</tr>
							</table>
							<table width="100%" border="0" cellspacing="0" cellpadding="5" class="product-table">
								@foreach($pendingItems as $product)
								<tr>
									<td style="width:60px;"><img src="{{ $product->image }}" alt="Product" style="width:60px; border-radius:3px;"></td>
									<td>
										<strong>{{ $product->name }}</strong><br>
										<span>Arriving </span><span style="color:#26683A; font-style:italic;">{{ $product->expectedDelivery }}</span>
									</td>
									<td class="quantity" style="text-align:right; font-weight:500;">{{ $product->quantity }}</td>
								</tr>
								@endforeach
							</table>

							<table width="100%" border="0" cellspacing="0" cellpadding="0">
								<tr>
									<td style="font-size:14px; padding:15px 0; border-top:3px solid #E2DFDF;">
										You can view or update your order anytime by visiting the
										<a href="{{ $orderUrl }}" class="order-link" style="font-weight:500; text-decoration:underline;">Orders</a> section under your account profile.
									</td>
								</tr>
								<tr>
									<td style="font-size:14px; line-height:24px; padding-top:10px; padding-bottom: 5px;  border-top:3px solid #E2DFDF;">We understand things change - and that’s okay.</td>
								</tr>
								<tr>
									<td style="font-size:14px; line-height:20px; padding-top:2px;">Whether now or later, we’ll be right here with great prices, honest service, and the support your business deserves.</td>
								</tr>
								<tr>
									<td style="font-size:14px; line-height:24px; padding-top:5px;">Changed your mind?
										<a href="{{ $checkoutUrl }}" class="order-link" style="text-decoration:underline; font-weight:500;">Click here to reorder</a> - we’d love to serve you again.
									</td>
								</tr>
								<tr>
									<td style="font-family: 'Noto Sans', sans-serif; color:#26683A; font-weight:500; font-size:14px; padding-top:5px;">&ndash; Team HorecaStore</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td style="font-family: 'Noto Sans', sans-serif; font-size:12px; color:#3F3F3F; background-color: rgba(226, 232, 240, 0.3); padding:20px; border-top:2px solid #E2E8F0;">
							<p style="margin:0;">&copy;2025 HorecaStore.ae. All rights reserved. HorecaStore, TheHorecaStore.com, and the HorecaStore.ae logo are trademarks of Horeca Store LLC or its affiliates.</p>
							<p style="margin:8px 0 0;">This message was sent from a notification-only email address that cannot receive incoming messages. Please do not reply to this email.</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
