<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<title>HorecaStore Email</title>
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
		We've received your request for Order {{ $orderNumber }}.
	</span>

	<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #ffffff; font-family: 'Noto Sans', sans-serif;">
		<tr>
			<td align="center" style="padding: 20px; font-family: 'Noto Sans', sans-serif;">
				<table border="0" cellpadding="0" cellspacing="0" width="600" style="border: 1px solid #eaeaea; font-family: 'Noto Sans', sans-serif;">
					<tr>
						<td align="left" style="padding: 20px; font-family: 'Noto Sans', sans-serif;">
							<img src="{{ $logoUrl }}" alt="Logo" width="120" style="display: block;" />
						</td>
					</tr>

					<tr>
						<td align="left" style="padding: 0 20px; font-size: 16px; font-family: 'Noto Sans', sans-serif;">
							<p style="margin: 0; padding: 5px 0; font-family: 'Noto Sans', sans-serif;">Hi <strong style="color:#26683A; font-family: 'Noto Sans', sans-serif;">{{ $name }}</strong>,</p>
							<p style="margin: 0; padding: 5px 0; font-family: 'Noto Sans', sans-serif; font-size: 14px;">We've received your post-purchase Price Match claim for Order <strong style="color:#26683A; font-family: 'Noto Sans', sans-serif;">{{ $orderNumber }}</strong>. Thank you for sharing the competitor's price with us.</p>
						</td>
					</tr>

					<tr>
						<td align="left" style="padding: 0 20px; font-size: 15px; font-family: 'Noto Sans', sans-serif;">
							<p style="font-weight: bold; margin: 0; padding: 10px 0; font-family: 'Noto Sans', sans-serif; color:#26683A; background-color: #e8f4fd; padding: 8px 12px; border-radius: 4px;">What happens next:</p>
							<table cellpadding="0" cellspacing="0" border="0" width="100%" style="font-family: 'Noto Sans', sans-serif; margin-top: 10px;">
								<tr>
									<td valign="top" style="color:#26683A; padding-bottom: 8px; font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="26" height="26" style="vertical-align: middle;" />
										<strong style="color:#232425; margin-left: 5px; font-family: 'Noto Sans', sans-serif; font-size: 14px;">Our team will verify the competitor's price and product details.</strong>
									</td>
								</tr>
								<tr>
									<td valign="top" style="color:#26683A; padding-bottom: 8px; font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="26" height="26" style="vertical-align: middle;" />
										<strong style="color:#232425; margin-left: 5px; font-family: 'Noto Sans', sans-serif; font-size: 14px;">If it qualifies, we'll issue a refund of the difference to your original payment method.</strong>
									</td>
								</tr>
								<tr>
									<td valign="top" style="color:#26683A; font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="26" height="26" style="vertical-align: middle;" />
										<strong style="color:#232425; margin-left: 5px; font-family: 'Noto Sans', sans-serif; font-size: 14px;">You can expect an update/refund within 7–10 business days.</strong>
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<tr>
						<td align="left" style="padding: 10px 20px; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
							<p style="margin: 0; padding: 5px 0; font-family: 'Noto Sans', sans-serif; font-size: 14px;">Your claim ID: <strong style="color:#26683A; font-family: 'Noto Sans', sans-serif;">HS-PM-{{ $claimId }}</strong></p>
							<p style="margin: 0; padding: 5px 0; font-family: 'Noto Sans', sans-serif; font-size: 14px;">No further steps are needed from you right now — we’ll notify you as soon as the review is complete.</p>
							<p style="margin: 0; padding: 5px 0; font-family: 'Noto Sans', sans-serif; font-size: 14px;">Thank you for trusting HorecaStore. We’re committed to keeping your purchases protected.</p>
						</td>
					</tr>

					<tr>
						<td align="left" style="padding: 10px 20px; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
							<p style="font-weight: 500; line-height: 24px; margin: 0; padding: 0; font-family: 'Noto Sans', sans-serif; font-size:14px; ">Warm regards,</p>
							<p style="color: #26683A; font-weight: 500; margin: 0; padding: 0; font-family: 'Noto Sans', sans-serif; font-size:14px; ">– Team HorecaStore</p>
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