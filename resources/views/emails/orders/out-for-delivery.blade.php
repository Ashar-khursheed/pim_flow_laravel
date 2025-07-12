<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<title>Order Out for Delivery</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@300;400;500;600;700&display=swap"
	rel="stylesheet">
	<style>
		@media only screen and (max-width: 600px) {
			.container {
				width: 100% !important;
				padding: 20px !important;
			}

			.footer-note {
				font-size: 11px !important;
				line-height: 1.4 !important;
			}
		}
	</style>
</head>

<body style="margin: 0; padding: 0; background: #ffffff; font-family: 'Noto Sans', sans-serif;">
	<!-- Preheader text: hidden but visible in email previews -->
	<span style="display: none; font-size: 1px; color: #ffffff; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
		Your delivery is en route. Make sure someone is available to receive it.
	</span>

	<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f8f8f8; font-family: 'Noto Sans', sans-serif;">
		<tr>
			<td align="center" style="font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 20px;">
				<table class="container" width="650" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border: 1px solid #eaeaea; font-family: 'Noto Sans', sans-serif;">
					<tr>
						<td style="padding: 20px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 20px;">
							<img src="{{ $logoUrl }}" alt="HORECA Logo" width="120" style="display: block; margin-bottom: 20px;">
							<p style="font-size: 16px;color: black;font-family: 'Noto Sans', sans-serif;line-height: 24px; margin: 5px  0 ;padding: 0;">
								Hello <strong style="color: #26683A; font-family: 'Noto Sans', sans-serif; line-height: 24px;">{{ $name }}</strong>!
							</p>
							<p style="font-size: 16px;color: black;font-family: 'Noto Sans', sans-serif;line-height: 24px;margin: 0;padding: 0;">
								<strong>Good news! Your HorecaStore order is on its way.</strong><br />
								You can expect it to arrive at your door shortly - track it anytime below.
							</p>

							<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 15px 0 0; font-family: 'Noto Sans', sans-serif;">
								<tr>
									<td valign="top" width="50%" style="padding: 0; padding-right: 10px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 20px;">
										<p style="font-weight: bold; font-size: 15px; color: #26683A; margin: 0 0 5px 0; font-family: 'Noto Sans', sans-serif; line-height: 22px;">
											Order Summary
										</p>
										<p style="font-size: 14px; font-weight: 600; color: black; margin: 0; font-family: 'Noto Sans', sans-serif; line-height: 20px;">
											Carrier:<strong style="font-weight: 500; font-family: 'Noto Sans', sans-serif;"> {{ $carrier }}</strong>
										</p>
										<p style="font-size: 14px; font-weight: 600; color: black; margin: 0; font-family: 'Noto Sans', sans-serif; line-height: 20px;">
											Order Number:<strong style="font-weight: 500; font-family: 'Noto Sans', sans-serif;"> {{ $orderNumber }}</strong>
										</p>
										<p style="font-size: 14px; font-weight: 600; color: black; margin: 0; font-family: 'Noto Sans', sans-serif; line-height: 20px;">
											Arriving:<strong style="font-weight: 500; font-family: 'Noto Sans', sans-serif;"> {{ $estimatedDeliveryFormatted }}</strong>
										</p>
										<p style="font-size: 14px; font-weight: 600; color: black; margin: 0; font-family: 'Noto Sans', sans-serif; line-height: 20px;">
											Payment Method:<strong style="font-weight: 500; font-family: 'Noto Sans', sans-serif;"> {{ $paymentMethod }}</strong>
										</p>
									</td>
									<td valign="top" width="50%" style="padding: 0; padding-left: 10px; border-left: 1px solid #26683A; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 20px;">
										<p style="font-weight: bold; font-size: 15px; color: #26683A; margin: 0 0 5px 0; font-family: 'Noto Sans', sans-serif; line-height: 22px;">
											Delivering to our valued customer
										</p>
										<p style="font-size: 14px; color: black; margin: 0; font-family: 'Noto Sans', sans-serif; line-height: 20px;">
											<strong>{{ $name }}</strong>
										</p>
										<p style="font-size: 14px; color: #26683A; margin: 0; font-family: 'Noto Sans', sans-serif; line-height: 20px;">
											{{ $address }}
										</p>
										<p style="font-size: 14px; color: #26683A; margin: 0; font-family: 'Noto Sans', sans-serif; line-height: 20px;">
											{{ $city }}
										</p>
										<p style="font-size: 14px; color: #26683A; margin: 0; font-family: 'Noto Sans', sans-serif; line-height: 20px;">
											{{ $country }}, {{ $zipcode }}
										</p>
									</td>
								</tr>
							</table>

							<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 20px 0; font-family: 'Noto Sans', sans-serif;">
								<tr>
									<td align="left" style="font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 20px;">
										<a href="{{ $orderDetailUrl }}" style="background-color: #26683A; color: #ffffff; padding: 12px 24px; text-decoration: none; font-size: 14px; border-radius: 5px; font-family: 'Noto Sans', sans-serif; display: inline-block; line-height: 20px;">
											Track Your Delivery
										</a>
									</td>
								</tr>
							</table>

							<p style="font-size: 15px; font-weight: 500; margin: 0 0 10px 0; font-family: 'Noto Sans', sans-serif; line-height: 22px; color: #000; font-weight: 500;">
								We’re proud to support your business with:
							</p>
							<table width="100%" cellpadding="0" cellspacing="0" border="0" style="font-family: 'Noto Sans', sans-serif;">
								<tr>
									<td style="padding: 0; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 20px;">
										<p style="font-size: 14px; display: flex; align-items: center; margin: 0 0 5px 0; font-family: 'Noto Sans', sans-serif; line-height: 20px; color: #000; font-weight: 500;">
											<img src="{{ $rightPngURL }}" alt="right" width="26" height="26" style="margin-right: 5px;">
											Guaranteed Lowest Prices
										</p>
										<p style="font-size: 14px; display: flex; align-items: center; margin: 0 0 5px 0; font-family: 'Noto Sans', sans-serif; line-height: 20px; color: #000; font-weight: 500;">
											<img src="{{ $rightPngURL }}" alt="right" width="26" height="26" style="margin-right: 5px;">
											Zero-Stress Experience
										</p>
										<p style="font-size: 14px; display: flex; align-items: center; margin: 0; font-family: 'Noto Sans', sans-serif; line-height: 20px; color: #000; font-weight: 500;">
											<img src="{{ $rightPngURL }}" alt="right" width="26" height="26" style="margin-right: 5px;">
											Reliable Quality You Can Count On
										</p>
									</td>
								</tr>
							</table>

							<p style="margin-top: 10px; font-size: 14px; color: #000; padding-top: 20px; margin: 0; font-family: 'Noto Sans', sans-serif; line-height: 20px;">
								Less stress. Better prices. More value.
							</p>
							<p style="color: #26683A; font-weight: 500; margin: 0; font-size: 14px; font-family: 'Noto Sans', sans-serif; line-height: 20px;">
								&ndash; Team HorecaStore
							</p>
						</td>
					</tr>
				</table>

				<table class="footer-note" width="650" cellpadding="20" cellspacing="0" border="0" style="border-top: 3px solid #E2E8F0; background-color: rgba(226,232,240,0.3); font-size: 12px; color: black; font-family: 'Noto Sans', sans-serif;">
					<tr>
						<td style="font-family: 'Noto Sans', sans-serif; font-size: 12px; line-height: 18px;">
							<p style="margin: 0; font-family: 'Noto Sans', sans-serif; font-size: 12px; line-height: 18px;">
								©{{ now()->year }} {{ $siteUrl }}. All rights reserved. HorecaStore, TheHorecaStore.com,
								and the HorecaStore.ae logo are trademarks of Horeca Store LLC or its affiliates.
							</p>
							<br/>
							<p style="margin: 0; font-family: 'Noto Sans', sans-serif; font-size: 12px; line-height: 18px;">
								This message was sent from a notification-only email address. Please do not reply directly to this email. For support or inquiries, contact us at
								<a href="mailto:{{ $siteEmail }}" style="color: black; text-decoration: none; font-family: 'Noto Sans', sans-serif; font-size: 12px; line-height: 18px;">
									{{ $siteEmail }}
								</a>
							</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>