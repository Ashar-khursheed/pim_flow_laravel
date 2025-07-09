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

			.details {
				display: block !important;
				width: 100% !important;
			}

			.details>div {
				width: 100% !important;
				margin-bottom: 20px !important;
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

	<div style="margin: auto; max-width:600px; background-color: #f8f8f8; font-family: 'Noto Sans', sans-serif;  ">
		<div class="container" style="font-family: 'Noto Sans', sans-serif;   background-color:  #ffffff; padding: 20px; border: 1px solid #eaeaea; box-sizing: border-box;">

			<!-- Logo -->
			<div style="margin-bottom: 20px;">
				<img src="{{ $logoUrl }}" alt="HORECA Logo" style="width: 120px;" />
			</div>

			<!-- Greeting -->
			<p style="font-family: 'Noto Sans', sans-serif;  font-size: 16px; color: #3F3F3F;">Hello <strong style="color: #26683A;">{{ $name }}</strong>!</p>
			<p style="font-size: 15px; color: #3F3F3F; line-height: 23px; font-family: 'Noto Sans', sans-serif; font-weight: 500; ">
				<strong>Good news! Your HorecaStore order is on its way.</strong><br />
				You can expect it to arrive at your door shortly - track it anytime below.
			</p>

			<div class="details" style="font-family: 'Noto Sans', sans-serif;  display: flex; justify-content: space-between; flex-wrap: wrap; margin: 20px 0;">
				<div style="width: 45%;">
					<p style="font-family: 'Noto Sans', sans-serif;  font-weight: bold; font-size: 15px; color: #26683A;  margin: 0; line-height: 30px;">
						Order Summary
					</p>
					<p style="font-family: 'Noto Sans', sans-serif;  font-size: 14px; font-weight: 600; color:#3F3F3F;  margin: 0; line-height: 23px;">
						Carrier<strong style="font-weight: 500;  color:#3F3F3F; ">:</strong><strong style="font-weight: 500; color:#3F3F3F;">{{ $carrier }}</strong>
					</p>
					<p style="font-family: 'Noto Sans', sans-serif;  font-size: 14px; font-weight: 600; color:#3F3F3F;  margin: 0; line-height: 23px;">
						Order Number<strong style="font-weight: 500;color:#3F3F3F;">:</strong>
						<strong style="font-weight: 500; color:#3F3F3F;">{{ $orderNumber }}</strong>
					</p>
					<p style="font-family: 'Noto Sans', sans-serif;  font-size: 14px; font-weight: 600; color:#3F3F3F; margin: 0; line-height: 23px; ">
						Arriving<strong style="font-weight: 500; color:#3F3F3F; ">:</strong>
						<strong style="font-weight: 500; color:#3F3F3F;">{{ $estimatedDeliveryFormatted }}</strong>
					</p>
					<p style=" font-family: 'Noto Sans', sans-serif;  font-size: 14px; font-weight: 600; color:#3F3F3F;  margin: 0; line-height: 23px;">
						Payment Method <strong style="font-weight: 500; color:#3F3F3F;">:</strong>
						<strong style="font-weight: 500; color:#3F3F3F;">{{ $paymentMethod }}</strong>
						{{-- <strong style="color: #26683A; font-weight: 500;">(5678)</strong> --}}
					</p>
				</div>

				<hr style="color: #26683A; font-family: 'Noto Sans', sans-serif; margin: 0; " />

				<div style="width: 50%; font-family: 'Noto Sans', sans-serif; ">
					<p style="font-family: 'Noto Sans', sans-serif;  font-weight: bold; font-size: 15px; color: #26683A;  margin: 0; line-height: 30px;">
						Delivering to our valued customer
					</p>

					<p style=" font-family: 'Noto Sans', sans-serif;  font-size: 14px; color: #3F3F3F;  margin: 0; line-height: 23px;">
						<strong>{{ $name }}</strong>
					</p>
					<p style="font-family: 'Noto Sans', sans-serif;  color: #26683A; font-size: 14px; margin: 0; line-height: 23px;">
						{{ $address }}
					</p>
					<p style="font-family: 'Noto Sans', sans-serif;  color: #26683A; font-size: 14px;  margin: 0;  line-height: 23px;">
						{{ $city }}
					</p>
					<p style="font-family: 'Noto Sans', sans-serif;  color: #26683A; font-size: 14px;  margin: 0;  line-height: 23px;">
						{{ $country }}, {{ $zipcode }}
					</p>
				</div>
			</div>

			<!-- Track Button -->
			<div style="margin: 30px 0;font-family: 'Noto Sans', sans-serif;  ">
				<a href="{{ $orderDetailUrl }}" style="font-family: 'Noto Sans', sans-serif;  background-color: #26683A; color: #ffffff; padding: 12px 24px; text-decoration: none; font-size: 14px; border-radius: 5px; display: inline-block;">Track Your Delivery</a>
			</div>

			<!-- Benefits Section -->
			<div style="margin-top: 20px; font-family: 'Noto Sans', sans-serif;  ">
				<p style="font-family: 'Noto Sans', sans-serif;  font-size: 15px; font-weight: 500; margin:2px 0;">
					We’re proud to support your business with:
				</p>
				<p style="font-family: 'Noto Sans', sans-serif;  font-size: 14px; display: flex; align-items: center; margin:10px 0 0 0;">
					<img src="{{ $rightPngURL }}" alt="right" width="26px" height="26px" style="margin-right: 5px;">
					Guaranteed Lowest Prices
				</p>
				<p style="font-family: 'Noto Sans', sans-serif; font-size: 14px; display: flex; align-items: center; margin:2px 0;">
					<img src="{{ $rightPngURL }}" alt="right" width="26px" height="26px" style="margin-right: 5px;">
					Zero-Stress Experience
				</p>
				<p style="font-family: 'Noto Sans', sans-serif;  font-size: 14px; display: flex; align-items: center; margin: 2px 0;">
					<img src="{{ $rightPngURL }}" alt="right" width="26px" height="26px" style="margin-right: 5px;">
					Reliable Quality You Can Count On
				</p>
			</div>

			<p style="font-family: 'Noto Sans', sans-serif;  margin-top: 10px; font-size: 14px; color: #000; padding-top: 20px;  margin: 2px 0;">
				Less stress. Better prices. More value.
			</p>

			<p style="font-family: 'Noto Sans', sans-serif;  color: #26683A; font-weight: 500; margin: 2px 0; font-size: 14px;">
				– Team HorecaStore
			</p>

		</div>
		<div class="footer-note" style="font-family: 'Noto Sans', sans-serif;  border-top: 3px solid #E2E8F0; padding: 20px; font-size: 12px; color:#3F3F3F; background-color: rgba(226, 232, 240, 0.3);">
			<p style="font-family: 'Noto Sans', sans-serif;  margin: 0;">
				©2025 HorecaStore.ae. All rights reserved. HorecaStore, TheHorecaStore.com, and the HorecaStore.ae logo are trademarks of Horeca Store LLC or its affiliates.
			</p>
			<br />
			<p style="font-family: 'Noto Sans', sans-serif;  margin: 0;">
				This message was sent from a notification-only email address. Please do not reply directly to this email. For support or inquiries, contact us at
				<a href="mailto:{{ $siteEmail }}" style="font-family: 'Noto Sans', sans-serif;  color: #3F3F3F; text-decoration: none;">{{ $siteEmail }}</a>
			</p>
		</div>
	</div>
</body>
</html>