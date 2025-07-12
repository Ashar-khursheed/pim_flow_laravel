<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<title>HORECA Email</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<style>
		@media only screen and (max-width: 580px) {
			.wrapper { width: 100% !important; padding: 20px !important; font-family: 'Noto Sans', sans-serif !important; }
			.footermain { display: block !important; font-family: 'Noto Sans', sans-serif !important; }
			.footerchild { width: 100% !important; margin-bottom: 15px !important; text-align: center !important; font-family: 'Noto Sans', sans-serif !important; }
		}
	</style>
</head>
<body style="margin:0; padding:0; background:#ffffff; font-family: 'Noto Sans', sans-serif; color:#232425;">
	<!-- Preheader text: hidden but visible in email previews -->
	<span style="display: none; font-size: 1px; color: #ffffff; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden; font-family: 'Noto Sans', sans-serif;">
		Your order is locked in and being prepared for dispatch. You’ll be updated soon.
	</span>

	<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #ffffff; font-family: 'Noto Sans', sans-serif;">
		<tr>
			<td align="center" style="padding: 20px; font-family: 'Noto Sans', sans-serif;">
				<table border="0" cellpadding="0" cellspacing="0" width="600" style="border: 1px solid #eaeaea; font-family: 'Noto Sans', sans-serif;">
					<tr>
						<td align="left" style="padding: 20px; font-family: 'Noto Sans', sans-serif;">
							<img src="{{ $logoUrl }}" alt="HORECA Logo" width="120" style="display: block;" />
						</td>
					</tr>

					<tr>
						<td align="left" style="padding: 0 20px; font-size: 16px; font-family: 'Noto Sans', sans-serif;">
							<p style="margin: 0; padding: 5px 0; font-family: 'Noto Sans', sans-serif;">Hello <strong style="color:#26683A; font-family: 'Noto Sans', sans-serif;">{{ $name }}</strong>!</p>
							<p style="margin: 0; padding: 5px 0; font-family: 'Noto Sans', sans-serif;">Great news - Your HorecaStore order <strong style="color:#26683A; font-family: 'Noto Sans', sans-serif;">#{{ $orderNumber }}</strong> has been confirmed and is now being processed.</p>
						</td>
					</tr>

					<tr>
						<td align="left" style="padding: 0 20px; font-size: 15px; font-family: 'Noto Sans', sans-serif;">
							<p style="font-weight: bold; margin: 0; padding: 10px 0; font-family: 'Noto Sans', sans-serif;">What happens next:</p>
							<table cellpadding="0" cellspacing="0" border="0" width="100%" style="font-family: 'Noto Sans', sans-serif;">
								<tr>
									<td valign="top" style="color:#26683A; padding-bottom: 8px; font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="26" height="26" style="vertical-align: middle;" />
										<strong style="color:#232425; margin-left: 5px; font-family: 'Noto Sans', sans-serif;">We’re preparing and packing your items</strong>
									</td>
								</tr>
								<tr>
									<td valign="top" style="color:#26683A; padding-bottom: 8px; font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="26" height="26" style="vertical-align: middle;" />
										<strong style="color:#232425; margin-left: 5px; font-family: 'Noto Sans', sans-serif;">You’ll receive shipping details shortly</strong>
									</td>
								</tr>
								<tr>
									<td valign="top" style="color:#26683A; font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="26" height="26" style="vertical-align: middle;" />
										<strong style="color:#232425; margin-left: 5px; font-family: 'Noto Sans', sans-serif;">Fast, trackable delivery is on its way</strong>
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<tr>
						<td align="left" style="padding:10px 20px; font-family: 'Noto Sans', sans-serif;">
							<a href="{{ $orderUrl }}" style="background-color:#26683A; color:#ffffff; padding:12px 24px; text-decoration:none; font-size:14px; border-radius:5px; display:inline-block; font-family: 'Noto Sans', sans-serif;">View Order Details</a>
						</td>
					</tr>

					<tr>
						<td align="left" style="padding: 0 20px; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
							<p style="font-weight: 500; padding: 5px 0; margin: 0; font-family: 'Noto Sans', sans-serif;">Need support? Real people. Real help. Just a call away.</p>
							<table cellpadding="0" cellspacing="0" border="0" width="100%" style="font-family: 'Noto Sans', sans-serif;">
								<tr>
									<td style="color: #26683A; font-weight: 500; padding-bottom: 8px; font-family: 'Noto Sans', sans-serif;">
										📞 <span style="color:#8B4513; font-family: 'Noto Sans', sans-serif;">{{ $siteName == 'UAE' ? 'UAE Toll-Free:' : 'Toll-Free (USA):' }}</span>
										@if($siteName == 'UAE')
										<span style="color:#8B4513; font-family: 'Noto Sans', sans-serif;">800</span> <span style="color:#26683A; font-family: 'Noto Sans', sans-serif;">- HORECA (467-322)</span>
										@if($siteName == 'USA')
										<span style="color:#8B4513; font-family: 'Noto Sans', sans-serif;">1-866-4</span> <span style="color:#26683A; font-family: 'Noto Sans', sans-serif;">- HORECA (1-866-446-7322)</span>
										@else
										{!! $siteTollFreeContact !!}
										@endif
									</td>
								</tr>
								@if($siteName == 'UAE')
								<tr>
									<td style="color: #26683A; font-weight: 500; font-family: 'Noto Sans', sans-serif;">
										🌐 <span style="color:#8B4513; font-family: 'Noto Sans', sans-serif;">International:</span>
										<span style="color:#26683A; font-family: 'Noto Sans', sans-serif;">+971 </span><span style="color:#8B4513; font-family: 'Noto Sans', sans-serif;">4 224 5818</span>
									</td>
								</tr>
								@endif
							</table>
						</td>
					</tr>

					<tr>
						<td align="left" style="padding: 10px 20px; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
							<p style="font-weight: 500; line-height: 24px; margin: 0; padding: 0; font-family: 'Noto Sans', sans-serif; font-size:14px; ">Thank you for choosing HorecaStore - Built for People Who Serve Others.</p>
							<p style="color: #26683A; font-weight: 500; margin: 0; padding: 0; font-family: 'Noto Sans', sans-serif; font-size:14px; ">– Team HorecaStore</p>
						</td>
					</tr>
				</table>

				<table border="0" cellpadding="0" cellspacing="0" width="600" style="border-top: 2px solid #E2E8F0; background-color: rgba(226, 232, 240, 0.3); font-family: 'Noto Sans', sans-serif;">
					<tr>
						<td align="left" style="padding: 20px; font-size: 12px; color:#3F3F3F; font-family: 'Noto Sans', sans-serif;">
							<p style="margin: 0; font-family: 'Noto Sans', sans-serif;">©{{ now()->year }} {{ $siteUrl }}. All rights reserved. HorecaStore, TheHorecaStore.com, and the HorecaStore.ae logo are trademarks of Horeca Store LLC or its affiliates.</p>
							<p style="margin: 8px 0 0; font-family: 'Noto Sans', sans-serif;">This message was sent from a notification-only address. Please do not reply directly to this email. For support or inquiries, contact us at {{ $siteEmail }}</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
