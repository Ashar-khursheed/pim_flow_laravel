<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<title>Order Cancellation - HorecaStore</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<style>
		@media only screen and (max-width: 580px) {
			.wrapper {
				width: 100% !important;
				padding: 20px !important;
			}
		}
	</style>
</head>

<body style="margin:0; padding:0; background: #ffffff; font-family:'Noto Sans', sans-serif; color:black;">
	<!-- Preheader text: hidden but visible in email previews -->
	<span style="display: none; font-size: 1px; color: #ffffff; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden; font-family: 'Noto Sans', sans-serif;">
		We're sorry to see your order go. Here's what was cancelled and how to reorder.
	</span>

	<table role="presentation" width="650" cellspacing="0" cellpadding="0" border="0" style="font-family: 'Noto Sans', sans-serif; background: #ffffff; margin: auto; ">
		<tr>
			<td align="center" style="font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">
				<table role="presentation" class="wrapper" width="650" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #eaeaea; padding:20px; font-family: 'Noto Sans', sans-serif;">
					<tr>
						<td align="left" style="padding-bottom: 20px; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">
							<img src="{{ $logoUrl }}" alt="Logo" width="120">
						</td>
					</tr>

					<tr>
						<td style="font-size: 16px;color:black; font-family: 'Noto Sans', sans-serif;line-height: 24px;">
							Hello <strong style="color:#26683A; font-family: 'Noto Sans', sans-serif;">{{ $name }}</strong>!
						</td>
					</tr>

					<tr>
						<td style="font-size: 14px;color:black; font-family: 'Noto Sans', sans-serif;line-height: 25px;">
							We noticed that your recent HorecaStore order <strong style="color:#26683A; font-family: 'Noto Sans', sans-serif;">#{{ $orderNumber }}</strong> was cancelled.
						</td>
					</tr>

					<tr>
						<td style="font-size:14px;color:black;padding: 10px 0;font-family: 'Noto Sans', sans-serif;line-height: 18px;">
							If this was an error, or if you had any trouble during the process, we’d be happy to help you place a new order or answer any questions you may have.
						</td>
					</tr>

					<tr>
						<td style="padding:10px 0 20px; border-top:2px solid #E2DFDF; border-bottom:2px solid #E2DFDF; text-align:left; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">
							<h3 style="font-size:16px; color:black; margin:5px 0; font-family: 'Noto Sans', sans-serif; line-height:22px;">
								Need help with your reorder?
							</h3>
							<a href="{{ $checkoutURL }}" style="background-color:#26683A; color:#ffffff; padding:12px 24px; text-decoration:none; font-size:14px; border-radius:5px; display:inline-block; font-family: 'Noto Sans', sans-serif; line-height:20px;">
								Reorder Now
							</a>
						</td>
					</tr>

					<tr>
						<td style="font-size:15px; color:#000000; padding-top:10px; padding-bottom:10px; font-family: 'Noto Sans', sans-serif; line-height:22px;">
							<p style="font-weight: 500;font-family: 'Noto Sans', sans-serif;font-size:15px;line-height: 20px;padding: 0;margin: 5px 0;">
								At HorecaStore, we understand plans change - and we’re always here to support your business with:
							</p>
							<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="font-family: 'Noto Sans', sans-serif;">
								<tr>
									<td style="width: 0%;font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="24" height="24">
									</td>
									<td valign="top" style="padding-left: 5px; font-family: 'Noto Sans', sans-serif;font-size:14px;line-height:25px;">
										<strong style="color:black; font-weight:500; font-family: 'Noto Sans', sans-serif;">Hassle-free service</strong>
									</td>
								</tr>
								<tr>
									<td  style="width: 0%; font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="24" height="24">
									</td>
									<td valign="top" style="padding-left: 5px; font-family: 'Noto Sans', sans-serif;font-size:14px;line-height:25px;">
										<strong style="color:black; font-weight:500; font-family: 'Noto Sans', sans-serif;">The lowest prices in the market</strong>
									</td>
								</tr>
								<tr>
									<td  style=" width: 0%; font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="24" height="24">
									</td>
									<td valign="top" style="padding-left: 5px;font-family: 'Noto Sans', sans-serif;font-size:14px;line-height:25px;">
										<strong style="color:black; font-weight:500; font-family: 'Noto Sans', sans-serif;">Real people, ready to help</strong>
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<tr>
						<td style="font-size:14px; color:black; border-top:2px solid #E2DFDF; padding-top:10px; font-family: 'Noto Sans', sans-serif; line-height:20px;">
							<p style="font-weight:500; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px; margin: 0;">
								We hope to see you back soon and make your next experience even better.
							</p>
							<p style="color:#26683A; font-size:14px; font-weight:500; margin:2px 0; font-family: 'Noto Sans', sans-serif; line-height:20px;">
								– Team HorecaStore
							</p>
						</td>
					</tr>
				</table>

				<table width="650" cellspacing="0" cellpadding="0" border="0" style="padding:10px; border-top:3px solid #E2E8F0; background-color: rgba(226, 232, 240, 0.3); font-size:11px; color:#3F3F3F;">
					<tr>
						<td>
							<p style="margin:5px 0; font-weight:500; font-size:14px; font-family: 'Noto Sans', sans-serif; line-height:20px;">
								MORE INFORMATION
							</p>
							<p style="margin:0; font-family: 'Noto Sans', sans-serif; font-size:12px; line-height:18px;">
								We strive to ship all orders promptly. However, in rare cases, delays or cancellations may occur. We appreciate your understanding and patience.
							</p>
							<p style="margin:5px 0; font-family: 'Noto Sans', sans-serif; font-size:12px; line-height:18px;">
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