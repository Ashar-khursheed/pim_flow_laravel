<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<title>HORECA Email</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<style>
		@media only screen and (max-width: 580px) {
			.wrapper { width: 100% !important; padding: 20px !important; }
			.footermain { display: block !important; }
			.footerchild { width: 100% !important; margin-bottom: 15px !important; text-align: center !important; }
		}
	</style>
</head>
<body style="margin:0; padding:0; background:#ffffff; font-family: 'Noto Sans', sans-serif; color:#232425;">
	<!-- Preheader text: hidden but visible in email previews -->
	<span style="display: none; font-size: 1px; color: #ffffff; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
		Your order is locked in and being prepared for dispatch. You’ll be updated soon.
	</span>


	<table border="0" cellpadding="0" cellspacing="0" width="100%" style="font-family: 'Noto Sans', sans-serif; background-color: #ffffff;">
		<tr>
			<td align="center" style="padding: 20px;">
				<table border="0" cellpadding="0" cellspacing="0" width="600" style="border: 1px solid #eaeaea;">
					<tr>
						<td align="left" style="padding: 20px;">
							<img src="{{ $logoUrl }}" alt="HORECA Logo" width="120" style="display: block;" />
						</td>
					</tr>

					<tr>
						<td align="left" style="padding: 0 20px; font-size: 16px;">
							<p style="margin: 0; padding: 5px 0;">Hello <strong style="color:#26683A;">{{ $name }}</strong>!</p>
							<p  style="margin: 0; padding: 5px 0;">Great news - Your HorecaStore order <strong style="color:#26683A;">#{{ $orderNumber }}</strong> has been confirmed and is now being processed.</p>
						</td>
					</tr>

					<tr>
						<td align="left" style="padding: 0 20px; font-size: 15px;">
							<p style="font-weight: bold;  margin: 0; padding: 10px 0;">What happens next:</p>

							<table cellpadding="0" cellspacing="0" border="0" width="100%">
								<tr>
									<td valign="top" style="color:#26683A; padding-bottom: 8px;">
										<img src="{{ $rightPngURL }}" alt="right" width="26" height="26" style="vertical-align: middle;" />
										<strong style="color:#232425; margin-left: 5px;">We’re preparing and packing your items</strong>
									</td>
								</tr>
								<tr>
									<td valign="top" style="color:#26683A; padding-bottom: 8px;">
										<img src="{{ $rightPngURL }}" alt="right" width="26" height="26" style="vertical-align: middle;" />
										<strong style="color:#232425; margin-left: 5px;">You’ll receive shipping details shortly</strong>
									</td>
								</tr>
								<tr>
									<td valign="top" style="color:#26683A;">
										<img src="{{ $rightPngURL }}" alt="right" width="26" height="26" style="vertical-align: middle;" />
										<strong style="color:#232425; margin-left: 5px;">Fast, trackable delivery is on its way</strong>
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<tr>
						<td align="left" style="padding:10px 20px;">
							<a href="{{ $orderUrl }}" style="background-color:#26683A; color:#ffffff; padding:12px 24px; text-decoration:none; font-size:14px; border-radius:5px; display:inline-block;">View Order Details</a>
						</td>
					</tr>

					<tr>
						<td align="left" style="padding: 0 20px; font-size: 14px;">
							<p style="font-weight: 500; padding: 5px 0; margin: 0;">Need support? Real people. Real help. Just a call away.</p>

							<table cellpadding="0" cellspacing="0" border="0" width="100%">
								<tr>
									<td style="color: #26683A; font-weight: 500; padding-bottom: 8px;">
										📞 <span style="color:#8B4513;">{{ $siteName }} Toll-Free:</span>
										@if($siteName == 'UAE')
											<span style="color:#8B4513;">800</span> <span style="color:#26683A;">- HORECA (467-322)</span>
										@else
											{!! $siteTollFreeContact !!}
										@endif
									</td>
								</tr>
								<br/>
								<tr>
									<td style="color: #26683A; font-weight: 500;">
										🌐 <span style="color:#8B4513;">International:</span>
										@if($siteName == 'UAE')
											<span style="color:#26683A;">+971 </span><span style="color:#8B4513;">4 224 5818</span>
										@else
											{!! $siteInternationalContact !!}
										@endif
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<tr>
						<td align="left" style="padding: 10px 20px; font-size: 14px;">
							<p style="font-weight: 500; line-height: 24px; margin: 0; padding: 0;" >Thank you for choosing HorecaStore - Built for People Who Serve Others.</p>
							<p style="color: #26683A; font-weight: 500; margin: 0; padding: 0;">– Team HorecaStore</p>
						</td>
					</tr>
				</table>

				<table border="0" cellpadding="0" cellspacing="0" width="600" style="border-top: 2px solid #E2E8F0; background-color: rgba(226, 232, 240, 0.3);">
					<tr>
						<td align="left" style="padding: 20px; font-size: 12px; color:#3F3F3F;">
							<p style="margin: 0;">©2025 HorecaStore.ae. All rights reserved. HorecaStore, TheHorecaStore.com, and the HorecaStore.ae logo are trademarks of Horeca Store LLC or its affiliates.</p>
							<p style="margin: 8px 0 0;">This message was sent from a notification-only address. Please do not reply directly to this email. For support or inquiries, contact us at {{ $siteEmail }}</p>
						</td>
					</tr>
				</table>

			</td>
		</tr>
	</table>
</body>
</html>
