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
	<table width="100%" border="0" cellspacing="0" cellpadding="0" style="background:#f8f8f8; font-family: 'Noto Sans', sans-serif;">
		<tr>
			<td align="center" style="font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">
				<table width="600" border="0" cellspacing="0" cellpadding="0" class="container" style="background:#ffffff; border:1px solid #eaeaea; font-family: 'Noto Sans', sans-serif;">
					<tr>
						<td style="padding:20px; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">
							<table width="100%" border="0" cellspacing="0" cellpadding="0" style="font-family: 'Noto Sans', sans-serif;">
								<tr>
									<td style="padding-bottom:20px; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">
										<img src="{{ $logoUrl }}" alt="HORECA Logo" style="width:120px;">
									</td>
								</tr>
								<tr>
									<td style="font-size:16px; padding-bottom:10px; font-family: 'Noto Sans', sans-serif; line-height:22px;">
										Hello <strong style="color:#26683A; font-family: 'Noto Sans', sans-serif;">{{ $name }}</strong>!
									</td>
								</tr>
								<tr>
									<td style="font-size:14px; padding-bottom:15px; font-family: 'Noto Sans', sans-serif; line-height:20px;">
										You've successfully cancelled the following item(s) from your order.<br />We’ll notify you once the remaining items are shipped.
									</td>
								</tr>
							</table>

							<!-- Order Summary -->
							<table width="100%" border="0" cellspacing="0" cellpadding="5" style="font-size:14px; color:black; font-family: 'Noto Sans', sans-serif;">
								<tr>
									<td style="font-weight:500; font-size:14px; font-family: 'Noto Sans', sans-serif; line-height:20px;">
										Order Number
									</td>
									<td style="font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">
										:
									</td>
									<td style="font-weight:500; font-size:14px; font-family: 'Noto Sans', sans-serif; line-height:18px;">
										{{ $orderNumber }}
									</td>
								</tr>
								<tr>
									<td style="font-weight:500; font-size:14px; font-family: 'Noto Sans', sans-serif; line-height:20px;">
										Order Date
									</td>
									<td style="font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">
										:
									</td>
									<td style="font-weight:500; font-size:14px; font-family: 'Noto Sans', sans-serif; line-height:18px;">
										{{ $orderDate }}
									</td>
								</tr>
								<tr>
									<td style="font-weight:500; font-size:14px; font-family: 'Noto Sans', sans-serif; line-height:20px;">
										Amount Paid
									</td>
									<td style="font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">
										:
									</td>
									<td style="font-weight:500; font-size:14px; font-family: 'Noto Sans', sans-serif; line-height:18px;">
										{{ $currency }} {{ $paidAmount }}
									</td>
								</tr>
								<tr>
									<td style="font-weight:500; font-size:14px; font-family: 'Noto Sans', sans-serif; line-height:20px;">
										Payment Method
									</td>
									<td style="font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">
										:
									</td>
									<td style="font-weight:500; font-size:14px; font-family: 'Noto Sans', sans-serif; line-height:18px;">
										{{ $paymentMethod }}
									</td>
								</tr>
							</table>

							<!-- Cancelled Items Section -->
							<table width="100%" border="0" cellspacing="0" cellpadding="8" style="margin-top:15px; background-color:#FAFAFA; border-bottom:1px solid #26683A; font-family: 'Noto Sans', sans-serif;">
								<tr>
									<td style="font-weight:500; font-size:15px; color:#BE2535; font-family: 'Noto Sans', sans-serif; line-height:22px;">
										Cancelled Items
									</td>
									<td style="font-weight:500; font-size:15px; color:#BE2535; text-align:right; font-family: 'Noto Sans', sans-serif; line-height:22px;">
										Quantity
									</td>
								</tr>
							</table>

							<table width="100%" border="0" cellspacing="0" cellpadding="5" class="product-table" style="font-family: 'Noto Sans', sans-serif;">
								@foreach($cancelledItems as $product)
								<tr>
									<td style="width:60px; font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $product->image }}" alt="Product" style="width:60px; border-radius:3px;">
									</td>
									<td style="font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">
										<strong style="font-family: 'Noto Sans', sans-serif;">{{ $product->name }}</strong><br>
										<span style="font-family: 'Noto Sans', sans-serif;">Cancellation Reason:</span>
										<span style="font-style:italic; font-family: 'Noto Sans', sans-serif;">{{ $product->cancellationReason }}</span>
									</td>
									<td class="quantity" style="text-align:right; font-weight:500; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">
										{{ $product->quantity }}
									</td>
								</tr>
								@endforeach
							</table>

							<!-- Remaining Items Section -->
							<table width="100%" border="0" cellspacing="0" cellpadding="8" style="margin-top:15px; background-color:#e5f8e7; border-bottom:1px solid #26683A; font-family: 'Noto Sans', sans-serif;">
								<tr>
									<td style="font-weight:500; font-size:15px; font-family: 'Noto Sans', sans-serif; line-height:22px;">
										Items Still on Their Way
									</td>
									<td style="font-weight:500; font-size:15px; text-align:right; font-family: 'Noto Sans', sans-serif; line-height:22px;">
										Quantity
									</td>
								</tr>
							</table>

							<table width="100%" border="0" cellspacing="0" cellpadding="5" class="product-table" style="font-family: 'Noto Sans', sans-serif; padding-bottom: 10px;">
								@foreach($pendingItems as $product)
								<tr>
									<td style="width:60px; font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $product->image }}" alt="Product" style="width:60px; border-radius:3px;">
									</td>
									<td style="font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">
										<strong style="font-family: 'Noto Sans', sans-serif;">{{ $product->name }}</strong><br>
										<span style="font-family: 'Noto Sans', sans-serif;">Arriving </span>
										<span style="color:#26683A; font-style:italic; font-family: 'Noto Sans', sans-serif;">{{ $product->expectedDelivery }}</span>
									</td>
									<td class="quantity" style="text-align:right; font-weight:500; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">
										{{ $product->quantity }}
									</td>
								</tr>
								@endforeach
							</table>

							<table width="100%" border="0" cellspacing="0" cellpadding="0" style="font-family: 'Noto Sans', sans-serif;">
								<tr>
									<td style="font-size:14px; padding:15px 0; border-top:3px solid #E2DFDF; font-family: 'Noto Sans', sans-serif; line-height:20px;">
										You can view or update your order anytime by visiting the
										<a href="{{ $orderUrl }}" class="order-link" style="font-weight:500; text-decoration:underline; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">
											Orders
										</a>
										section under your account profile.
									</td>
								</tr>
								<tr>
									<td style="font-size:14px; line-height:24px; padding-top:10px; padding-bottom:5px; border-top:3px solid #E2DFDF; font-family: 'Noto Sans', sans-serif;">
										We understand things change - and that’s okay.
									</td>
								</tr>
								<tr>
									<td style="font-size:14px; line-height:20px; padding-top:2px; font-family: 'Noto Sans', sans-serif;">
										Whether now or later, we’ll be right here with great prices, honest service, and the support your business deserves.
									</td>
								</tr>
								<tr>
									<td style="font-size:14px; line-height:24px; padding-top:5px; font-family: 'Noto Sans', sans-serif;">
										Changed your mind?
										<a href="{{ $checkoutUrl }}" class="order-link" style="text-decoration:underline; font-weight:500; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">
											Click here to reorder
										</a>
										- we’d love to serve you again.
									</td>
								</tr>
								<tr>
									<td style="font-family: 'Noto Sans', sans-serif; color:#26683A; font-weight:500; font-size:14px; padding-top:5px; line-height:20px;">
										&ndash; Team HorecaStore
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td style="font-family: 'Noto Sans', sans-serif; font-size:12px; color:#3F3F3F; background-color: rgba(226, 232, 240, 0.3); padding:20px; border-top:2px solid #E2E8F0; line-height:18px;">
							<p style="margin:0; font-family: 'Noto Sans', sans-serif; font-size:12px; line-height:18px;">
								©{{ now()->year }} {{ $siteUrl }}. All rights reserved. HorecaStore, TheHorecaStore.com, and the HorecaStore.ae logo are trademarks of Horeca Store LLC or its affiliates.
							</p>
							<p style="margin:8px 0 0; font-family: 'Noto Sans', sans-serif; font-size:12px; line-height:18px;">
								This message was sent from a notification-only email address that cannot receive incoming messages. Please do not reply to this email.
							</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>