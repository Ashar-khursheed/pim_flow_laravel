<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<title>Welcome Email</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

	<style>
		body {
			font-family: 'Poppins', sans-serif;
		}

		@media only screen and (max-width: 600px) {
			.container {
				width: 100% !important;
				padding: 20px !important;
			}

			.social-icons {
				text-align: center !important;
				margin-top: 15px;
			}

			.footer-note {
				font-size: 11px !important;
				line-height: 1.4 !important;
			}

		}
	</style>
</head>

<body style="margin:0; padding:0; background:#ffffff; font-family: 'Poppins', sans-serif;  color:#000000;">

	<div style="width:100%; padding:20px 0; background:#f8f8f8; font-family: 'Poppins', sans-serif;">
		<div class="container" style="max-width:600px;  margin:0 auto; background:#ffffff; padding:30px; border:1px solid #eaeaea; box-sizing:border-box;">

			<!-- Logo -->
			<div style="margin-bottom:20px;">
				<img src="{{ $logoUrl }}" alt="Logo" style="width: 120px;" />
			</div>

			<!-- Greeting -->
			<p style="font-size:16px; color:#000000; margin: 0 0 10px; font-family: 'Poppins', sans-serif;">
				Hi <strong style="color:#186737;">{{ $name }}</strong>!
			</p>

			<p style="font-size:15px; color:#000000; margin: 0 0 20px; font-family: 'Poppins', sans-serif;">
				Starting or running a restaurant isn’t just business, it’s a dream. At HorecaStore, we’re here to help turn that dream into reality.
			</p>
			<p style="font-size:15px; color:#000000; margin: 0 0 20px; font-family: 'Poppins', sans-serif;">
				Your Default password is:<strong style="color:#186737;"> {{ $randomPassword }} </strong>
			</p>
			<p style="font-size:15px; color:#000000; margin: 0 0 20px; font-family: 'Poppins', sans-serif;">
				If you want to change your password click the button below.
				<br />
				<a href="{{ $resetPasswordUrl}}" style="background-color:#186737;  color:#ffffff; margin-top: 20px; padding:10px 10px; text-decoration:none; font-size:14px; border-radius:5px; display:inline-block;">
					Reset Password
				</a>
			</p>

			<!-- Value Points -->
			<p style="font-size:15px; font-weight:bold; margin:0 0 10px; font-family: 'Poppins', sans-serif;">
				Here’s what you can count on from us:
			</p>

			<div style=" margin: 0 0 20px; font-size:14px; color:#000000; font-family: 'Poppins', sans-serif;">
				<p style="color:green; font-family: 'Poppins', sans-serif;">
					✔ <strong style="color:#000000;">Lowest Prices Guaranteed</strong>
				</p>
				<p style="color:green; font-family: 'Poppins', sans-serif;">
					✔ <strong style="color:#000000;">No Hidden Fees</strong>
				</p>
				<p style="color:green; font-family: 'Poppins', sans-serif;">
					✔ <strong style="color:#000000;">{{ $regionName }} Longest Warranty on Equipment & Supplies</strong>
				</p>
				<p style="color:green; font-family: 'Poppins', sans-serif;">
					✔ <strong style="color:#000000;">Real Support From People Who Care</strong>
				</p>
			</div>

			<!-- Body Text -->
			<p style="font-size:14px; color:#333333; margin: 0 0 10px; font-family: 'Poppins', sans-serif;">
				From grand openings to your busiest nights, we’ve got you covered so you can focus on what matters most: bringing your vision to life.
			</p>
			<p style="font-size:14px; color:#333333; margin: 0 0 20px; font-family: 'Poppins', sans-serif;">
				You’ve got the vision. We’ve got your back.
			</p>

			<!-- CTA Button -->
			<div style="margin-bottom:20px; font-family: 'Poppins', sans-serif;">
				<a href="{{ $websiteUrl }}" style="background-color:#186737; color:#ffffff; padding:12px 24px; text-decoration:none; font-size:14px; border-radius:5px; display:inline-block;">Browse Products</a>
			</div>

			<!-- Final Message -->
			<p style="font-size:14px; color:#333333; margin:0 0 10px; font-family: 'Poppins', sans-serif;">
				Welcome to the smarter way to run your Restaurant.
			</p>
			<p style="font-size:14px; color:#333333; margin:0 0 10px; font-family: 'Poppins', sans-serif;">
				<strong>Save More, with Zero Stress.</strong>
			</p>
			<p style="font-size:14px; color:#186737; font-weight:bold; margin:0 0 20px; font-family: 'Poppins', sans-serif;">
				– Team HorecaStore
			</p>

			<!-- Footer -->
			<div class="footer-note" style=" font-family: 'Poppins', sans-serif; border-top:1px solid #ccc; margin-top:30px; padding-top:15px; font-size:12px; color:#777;">
				<p style="margin:0; font-family: 'Poppins', sans-serif;">
					©{{ now()->year }} {{ $siteUrl }}. All rights reserved. HorecaStore, TheHorecaStore.com, and the HorecaStore.ae logo are trademarks of HorecaStore LLC or its affiliates.
				</p>
				<p style="margin:8px 0 0; font-family: 'Poppins', sans-serif;">
					This message was sent from a notification-only email address. Please do not reply directly to this email. For support or inquiries, contact us at
					<a href="mailto:{{ $siteEmail }}" style="color:#186737; font-family: 'Noto Sans', sans-serif; font-size:12px; line-height:18px;">
						{{ $siteEmail }}
					</a>.
				</p>
			</div>
		</div>
	</div>
</body>
</html>