<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<title>Order Confirmed Successfully</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<style>
		@media only screen and (max-width: 600px) {
			.container {
				width: 100% !important;
				padding: 20px !important;
			}

			.order-button {
				display: block;
				margin: 15px 0;
			}
		}
	</style>
</head>
<body style="margin: 0; padding: 0; background: #ffffff; font-family: 'Noto Sans', sans-serif; color: black;">
	<!-- Preheader text: hidden but visible in email previews -->
	<span style="display: none; font-size: 1px; color: #ffffff; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
		Your order is locked in and being prepared for dispatch. You'll be updated soon.
	</span>

	<table width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f8f8f8; font-family: 'Noto Sans', sans-serif;">
		<tr>
			<td align="center">
				<table class="container" width="650" cellspacing="0" cellpadding="10" border="0" style="background:#ffffff; border:1px solid #eaeaea; font-family: 'Noto Sans', sans-serif;">
					<tr>
						<td align="left">
							<img src="{{ $logoUrl }}" alt="Logo" width="120">
						</td>
					</tr>

					<tr>
						<td>
							<p style="font-size:16px; line-height:25px; font-weight: 500; font-family: 'Noto Sans', sans-serif; margin: 0;">
								Hello
								<strong style="color:#26683A; font-family: 'Noto Sans', sans-serif; font-size:16px; line-height:25px; margin: 0;">
									{{ $name }}
								</strong>!
							</p>
							<p style="font-size:14px; line-height:25px; font-weight: 500; font-family: 'Noto Sans', sans-serif; margin: 0;">
								Great news - Your HorecaStore order <strong style="color:#26683A; font-family: 'Noto Sans', sans-serif;">#{{ $orderNumber }}</strong> has been confirmed and is now being processed.
							</p>

							<p style="font-size:14px; line-height:22px; font-family: 'Noto Sans', sans-serif; padding: 0; margin: 8px 0; font-weight: 600;">
								What happens next:
							</p>

							<table cellpadding="0" cellspacing="0" border="0" width="100%" style="font-family: 'Noto Sans', sans-serif; margin-bottom: 10px;">
								<tr>
									<td valign="top" style="color:#26683A; padding-bottom: 8px; font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="20" height="20" style="vertical-align: middle;" />
										<span style="color:#232425; margin-left: 5px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height:20px;">We're preparing and packing your items</span>
									</td>
								</tr>
								<tr>
									<td valign="top" style="color:#26683A; padding-bottom: 8px; font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="20" height="20" style="vertical-align: middle;" />
										<span style="color:#232425; margin-left: 5px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height:20px;">You'll receive shipping details shortly</span>
									</td>
								</tr>
								<tr>
									<td valign="top" style="color:#26683A; font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="20" height="20" style="vertical-align: middle;" />
										<span style="color:#232425; margin-left: 5px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height:20px;">Fast, trackable delivery is on its way</span>
									</td>
								</tr>
							</table>

							<a href="{{ $orderUrl }}" class="order-button" style="background:#26683A; color:#fff; padding:12px 24px; margin-top: 10px; font-size:14px; line-height:20px; text-decoration:none; border-radius:5px; display:inline-block; font-family: 'Noto Sans', sans-serif;">
								View Order Details
							</a>
						</td>
					</tr>

					<tr>
						<td>
							<table width="100%" cellspacing="0" cellpadding="0" border="0">
								<tr>
									<td style="font-size:14px; border-top:3px solid #E2E8F0; padding-top:15px; padding-bottom:5px; font-family: 'Noto Sans', sans-serif">
										<p style="font-weight: 500; font-size: 14px; padding: 5px 0; margin: 0; font-family: 'Noto Sans', sans-serif;">Need support? Real people. Real help. Just a call away.</p>
										<table cellpadding="0" cellspacing="0" border="0" width="100%" style="font-family: 'Noto Sans', sans-serif; margin-top: 5px;">
											<tr>
												<td style="color: #26683A; font-weight: 500; padding-bottom: 8px; font-family: 'Noto Sans', sans-serif; font-size: 14px;">
													📞 <span style="color:#8B4513; font-family: 'Noto Sans', sans-serif;">Toll-Free:</span>
													@if($siteName == 'UAE')
													<span style="color:#8B4513; font-family: 'Noto Sans', sans-serif;">800</span> <span style="color:#26683A; font-family: 'Noto Sans', sans-serif;">- HORECA (467-322)</span>
													@elseif($siteName == 'USA')
													<a href="tel:18664467322" style="color:#26683A; text-decoration:none; font-family: 'Noto Sans', sans-serif;">1-866-4- HORECA (1-866-446-7322)</a>
													@else
													{!! $siteTollFreeContact !!}
													@endif
												</td>
											</tr>
											@if($siteName == 'UAE')
											<tr>
												<td style="color: #26683A; font-weight: 500; font-family: 'Noto Sans', sans-serif; font-size: 14px;">
													🌐 <span style="color:#8B4513; font-family: 'Noto Sans', sans-serif;">International:</span>
													<span style="color:#26683A; font-family: 'Noto Sans', sans-serif;">+971 </span><span style="color:#8B4513; font-family: 'Noto Sans', sans-serif;">4 224 5818</span>
												</td>
											</tr>
											@endif
										</table>
									</td>
								</tr>
								<tr>
									<td style="font-size:14px; padding-top:15px; padding-bottom:5px;  font-family: 'Noto Sans', sans-serif">
										You can view or update your order anytime by visiting the Orders section under your account profile.
									</td>
								</tr>
								<tr>
									<td style="font-weight: 500; line-height: 24px; margin: 0; padding: 0; font-family: 'Noto Sans', sans-serif; font-size:14px;">
										Thank you for choosing HorecaStore - where your business gets the best, for less.
									</td>
								</tr>
								<tr>
									<td style="font-size:14px; color: #26683A; font-weight: 500; line-height:22px; font-family: 'Noto Sans', sans-serif">
										<p style="padding: 5px 0; color: #26683A; font-weight: 500; font-size: 14px; margin: 0; line-height: 20px; font-family: 'Noto Sans', sans-serif;">
											&ndash; Team HorecaStore
										</p>
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>

				<table width="650" cellspacing="0" cellpadding="0" border="0" style="padding:10px; border-top:3px solid #E2E8F0; background-color: rgba(226, 232, 240, 0.3); font-size:11px; color:#3F3F3F;">
					<tr>
						<td>
							<p style="margin: 0;font-size:12px; font-family: 'Noto Sans', sans-serif;">
								©{{ now()->year }} {{ $siteUrl }}. All rights reserved. HorecaStore, TheHorecaStore.com, and the HorecaStore.ae logo are trademarks of HorecaStore LLC or its affiliates.
							</p>
							<p style="margin: 8px 0 0; font-size:12px; font-family: 'Noto Sans', sans-serif;">
								For support or inquiries, contact us at
								<a href="mailto:{{ $siteEmail }}" style="color:#186737; font-family: 'Noto Sans', sans-serif; font-size:12px; line-height:18px;">
									{{ $siteEmail }}
								</a>.
							</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>