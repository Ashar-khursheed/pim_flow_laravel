<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<title>HORECA Email</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@300;400;500;600;700&display=swap"
	rel="stylesheet">
	<style>
		@media only screen and (max-width: 580px) {
			.wrapper {
				width: 100% !important;
				padding: 20px !important;
			}

			.footermain {
				display: block !important;
			}

			.footerchild {
				width: 100% !important;
				margin-bottom: 15px !important;
				text-align: center !important;
			}

		}
	</style>
</head>

<body style=" margin:0; padding:0; background:#ffffff; font-family: 'Noto Sans', sans-serif; color:#000;">
	<!-- Preheader text: hidden but visible in email previews -->
	<span style="display: none; font-size: 1px; color: #ffffff; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
		Your order is locked in and being prepared for dispatch. You’ll be updated soon.
	</span>

	<div style=" font-family: 'Noto Sans', sans-serif;  width:100%; padding:20px; box-sizing:border-box;">
		<div class="wrapper" style=" font-family: 'Noto Sans', sans-serif;  max-width:600px; margin:0 auto; padding:30px; background-color:#ffffff; border:1px solid #eaeaea; box-sizing:border-box;">


			<div style=" font-family: 'Poppins', sans-serif;  margin-bottom:20px;">
				<img src="{{ $logoUrl }}" alt="HORECA Logo" style="width: 120px;" />
			</div>

			<div style=" font-family: 'Noto Sans', sans-serif;  font-size:16px; color:#000000;">
				<p>Hi <strong style=" font-family: 'Noto Sans', sans-serif;  color:#186737;">{{ $name }}</strong>!</p>
				<p>
					Great news — your HORECA order
					<strong style=" font-family: 'Noto Sans', sans-serif;  color:#186737;">#{{ $orderNumber }}</strong>
					has been confirmed and is now being processed.
				</p>
			</div>

			<div style=" font-family: 'Noto Sans', sans-serif;  padding-top:10px; font-size:15px; color:#000;">
				<p style=" font-family: 'Noto Sans', sans-serif;  font-weight:bold;">
					What happens next:
				</p>
				<p style=" font-family: 'Noto Sans', sans-serif;  margin: 8px 0; color:#186737;">✔
					<strong style=" font-family: 'Noto Sans', sans-serif;  color:black;">We’re preparing and packing your items</strong>
				</p>
				<p style=" font-family: 'Noto Sans', sans-serif;  margin: 8px 0; color:#186737;">✔
					<strong style=" font-family: 'Noto Sans', sans-serif;  color:black;">You’ll receive shipping details shortly</strong>
				</p>
				<p style=" font-family: 'Noto Sans', sans-serif;  margin: 8px 0; color:#186737;">✔
					<strong style=" font-family: 'Noto Sans', sans-serif;  color:black;">Fast, trackable delivery is on its way</strong>
				</p>
			</div>
			<div style=" font-family: 'Noto Sans', sans-serif;  padding-top:10px; padding-bottom:20px;">
				<a href="{{ $orderUrl }}" class="button" style=" font-family: 'Noto Sans', sans-serif;  background-color:#26683A; color:#ffffff; padding:12px 24px; text-decoration:none; font-size:14px; border-radius:5px; display:inline-block;">View Order Details</a>
			</div>

			<div style=" font-family: 'Noto Sans', sans-serif;  font-size:14px; color:#333; margin-bottom:10px;">
				<p>Need assistance? We’re here 24/7.</p>
				<p style=" font-family: 'Noto Sans', sans-serif;  display:flex; align-items:center; color: #186737;">
					<img src="Group.png" alt="phone" width="20" height="20" style=" font-family: 'Noto Sans', sans-serif;  margin-right:8px; color: #007c3e;" />
					<strong>{{ $siteName }}:</strong> {{ $siteContact }}
				</p>
			</div>

			<div style=" font-family: 'Noto Sans', sans-serif;  margin-top:20px; font-size:14px; color:#333;">
				<p>Thanks for choosing HORECA — the marketplace built for hotels, restaurants, and cafes like yours.</p>
				<p style=" font-family: 'Noto Sans', sans-serif;  color:#007c3e; font-weight:bold;">#TeamHoreca</p>
			</div>

			<!-- Footer -->
			<div class="footer-note" style=" font-family: 'Noto Sans', sans-serif; border-top:1px solid #ccc; margin-top:30px; padding-top:15px; font-size:12px; color:#777;">
				<p style="margin:0; font-family: 'Noto Sans', sans-serif; ">©2025 HorecaStore.ae. All rights reserved. HorecaStore, TheHorecaStore.com, and the HorecaStore.ae logo are trademarks of Horeca Store LLC or its affiliates.</p>
				<p style="margin:8px 0 0; font-family: 'Noto Sans', sans-serif; ">This message was sent from a notification-only address. Please do not us at: {{ $siteEmail }}</p>
			</div>

		</div>
	</div>
</body>
</html>