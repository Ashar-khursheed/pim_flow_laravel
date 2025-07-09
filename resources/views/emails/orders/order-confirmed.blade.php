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

<body style=" margin:0; padding:0; background:#ffffff; font-family: 'Noto Sans', sans-serif; color:#232425;">
	<!-- Preheader text: hidden but visible in email previews -->
	<span style="display: none; font-size: 1px; color: #ffffff; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
		Your order is locked in and being prepared for dispatch. You’ll be updated soon.
	</span>

	<div style=" font-family: 'Noto Sans', sans-serif; margin:0 auto;  max-width:600px; padding:0px; box-sizing:border-box; color:#232425;">
		<div class="wrapper" style=" font-family: 'Noto Sans', sans-serif;    padding:20px; background-color:#ffffff; border:1px solid #eaeaea; box-sizing:border-box;">

			<div style=" font-family: 'Noto Sans', sans-serif;  margin-bottom:20px;">
				<img src="{{ $logoUrl }}" alt="HORECA Logo" style=" font-family: 'Noto Sans', sans-serif;  width:120px;" />
			</div>

			<div style=" font-family: 'Noto Sans', sans-serif;  font-size:16px; color:#232425;">
				<p>Hello <strong style=" font-family: 'Noto Sans', sans-serif;  color:#26683A;">{{ $name }}</strong>!</p>
				<p style="color:#232425;">
					Great news — your HorecaStore order
					<strong style=" font-family: 'Noto Sans', sans-serif;  color:#26683A;">#{{ $orderNumber }}</strong>
					has been confirmed and is now being processed.
				</p>
			</div>

			<div style=" font-family: 'Noto Sans', sans-serif;   font-size:15px; color:#000;">
				<p style=" font-family: 'Noto Sans', sans-serif;  font-weight:bold;">What happens next:</p>
				<p style=" font-family: 'Noto Sans', sans-serif;  margin: 8px 0; color:#26683A; display: flex; align-items: center;">
					<img src="{{ $rightPngURL }}" alt="right" width="26px" height="26px">
					<strong style=" font-family: 'Noto Sans', sans-serif;  color:#232425; margin-left: 5px;">We’re preparing and packing your items</strong>
				</p>
				<p style=" font-family: 'Noto Sans', sans-serif;  margin: 8px 0; color:#26683A; display: flex; align-items: center;">
					<img src="{{ $rightPngURL }}" alt="right" width="26px" height="26px">

					<strong style=" font-family: 'Noto Sans', sans-serif;  color:#232425; margin-left: 5px;">You’ll receive shipping details shortly</strong>
				</p>
				<p style=" font-family: 'Noto Sans', sans-serif;  margin: 8px 0; color:#26683A; display: flex; align-items: center;">
					<img src="{{ $rightPngURL }}" alt="right" width="26px" height="26px">
					<strong style=" font-family: 'Noto Sans', sans-serif;  color:#232425; margin-left: 5px;">Fast, trackable delivery is on its way</strong>
				</p>
			</div>

			<div style=" font-family: 'Noto Sans', sans-serif;  padding-top:10px; padding-bottom:20px;">
				<a href="{{ $orderUrl }}" class="button" style=" font-family: 'Noto Sans', sans-serif;  background-color:#26683A; color:#ffffff; padding:12px 24px; text-decoration:none; font-size:14px; border-radius:5px; display:inline-block;">View Order Details</a>
			</div>

			<div style=" font-family: 'Noto Sans', sans-serif;  font-size:14px; color:#232425; margin-bottom:10px;">
				<p style="font-family: 'Noto Sans', sans-serif;   font-size:14px; color:#232425; font-weight: 500;">
				Need support? Real people. Real help. Just a call away.</p>
				<p style="font-family: 'Noto Sans', sans-serif; display: flex; flex-direction: column; gap: 8px; color: #26683A; font-weight: 500;">
					<span style="display: flex; align-items: center;">
						<span style="font-size: 14px; margin-right: 8px;">📞</span>
						<span style="color: #8B4513;">{{ $siteName }} Toll-Free:</span>&nbsp;
						{!! $siteTollFreeContact !!}
					</span>
					<span style="display: flex; align-items: center;">
						<span style="font-size: 14px; margin-right: 8px;">🌐</span>
						<span style="color: #8B4513;">International:</span>&nbsp;
						{!! $siteInternationalContact !!}
					</span>
				</p>
			</div>

			<div style=" font-family: 'Noto Sans', sans-serif;  margin-top:20px; font-size:14px; color:#232425;">
				<p style=" font-family: 'Noto Sans', sans-serif;  color: #232425; font-weight:500; line-height: 24px; font-size: 14px;">
					Thank you for choosing HorecaStore- Built for People Who Serve Others.
				</p>
				<p style="font-family: 'Noto Sans', sans-serif;  color: #26683A; font-weight: 500; margin: 2px 0; font-size: 14px;">
					– Team HorecaStore
				</p>
			</div>
		</div>
		<div class="footer-note" style=" font-family: 'Noto Sans', sans-serif; border-top:2px solid #E2E8F0; margin-top:0px; padding:20px; font-size:12px;background-color: rgba(226, 232, 240, 0.3);color:#3F3F3F;">
			<p style="margin:0; font-family: 'Noto Sans', sans-serif; ">
				©2025 HorecaStore.ae. All rights reserved. HorecaStore, TheHorecaStore.com, and the HorecaStore.ae logo are trademarks of Horeca Store LLC or its affiliates.
			</p>
			<p style="margin:8px 0 0; font-family: 'Noto Sans', sans-serif; ">
				This message was sent from a notification-only address. Please do not reply directly to this email. For support or inquiries, contact us at {{ $siteEmail }}
			</p>
		</div>
	</div>
</body>
</html>