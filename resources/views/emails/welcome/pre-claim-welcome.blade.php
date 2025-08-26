<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<title>Welcome Email</title>
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
		Your login details are inside. Manage orders, quotes, and invoices in one place.
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
							<p style="margin: 0; padding: 5px 0; font-family: 'Noto Sans', sans-serif; font-size: 15px;">Welcome to HorecaStore! We've created your account so you don't have to re-enter your details every time, making your experience simpler and faster.</p>
						</td>
					</tr>

					<tr>
						<td align="left" style="padding: 0 20px; font-size: 15px; font-family: 'Noto Sans', sans-serif;">
							<p style="font-weight: bold; margin: 0; padding: 10px 0; font-family: 'Noto Sans', sans-serif;">Here are your login details:</p>
							<table cellpadding="0" cellspacing="0" border="0" width="100%" style="font-family: 'Noto Sans', sans-serif; margin-bottom: 10px;">
								<tr>
									<td style="padding-bottom: 5px; font-family: 'Noto Sans', sans-serif; font-size: 14px;">
										<strong>Username:</strong> {{ $email }}
									</td>
								</tr>
								<tr>
									<td style="padding-bottom: 5px; font-family: 'Noto Sans', sans-serif; font-size: 14px;">
										<strong>Temporary Password:</strong> <strong style="color:#26683A;">{{ $randomPassword }}</strong>
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<tr>
						<td align="left" style="padding:10px 20px; font-family: 'Noto Sans', sans-serif;">
							<p style="margin: 0; padding: 5px 0; font-family: 'Noto Sans', sans-serif; font-size: 15px;">You can log in anytime here:</p>
							<a href="{{ $loginUrl }}" style="background-color:#26683A; color:#ffffff; padding:12px 24px; text-decoration:none; font-size:14px; border-radius:5px; display:inline-block; font-family: 'Noto Sans', sans-serif; margin-top: 10px;">Login</a>
							<p style="font-size:12px; color:#666666; margin: 10px 0 0; font-family: 'Noto Sans', sans-serif;">(For your security, please update your password after your first login.)</p>
						</td>
					</tr>

					<tr>
						<td align="left" style="padding: 0 20px; font-size: 15px; font-family: 'Noto Sans', sans-serif;">
							<p style="font-weight: bold; margin: 0; padding: 10px 0; font-family: 'Noto Sans', sans-serif;">With your new account, you can:</p>
							<table cellpadding="0" cellspacing="0" border="0" width="100%" style="font-family: 'Noto Sans', sans-serif;">
								<tr>
									<td valign="top" style="color:#26683A; padding-bottom: 8px; font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="26" height="26" style="vertical-align: middle;" />
										<strong style="color:#232425; margin-left: 5px; font-family: 'Noto Sans', sans-serif; font-size: 14px;">Save and track orders in one place</strong>
									</td>
								</tr>
								<tr>
									<td valign="top" style="color:#26683A; padding-bottom: 8px; font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="26" height="26" style="vertical-align: middle;" />
										<strong style="color:#232425; margin-left: 5px; font-family: 'Noto Sans', sans-serif; font-size: 14px;">Manage your quotes and invoices</strong>
									</td>
								</tr>
								<tr>
									<td valign="top" style="color:#26683A; padding-bottom: 8px; font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="26" height="26" style="vertical-align: middle;" />
										<strong style="color:#232425; margin-left: 5px; font-family: 'Noto Sans', sans-serif; font-size: 14px;">Access past order history with one click</strong>
									</td>
								</tr>
								<tr>
									<td valign="top" style="color:#26683A; font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="26" height="26" style="vertical-align: middle;" />
										<strong style="color:#232425; margin-left: 5px; font-family: 'Noto Sans', sans-serif; font-size: 14px;">Get personalized support from our team</strong>
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<tr>
						<td align="left" style="padding: 10px 20px; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
							<p style="margin: 0; padding: 5px 0; font-family: 'Noto Sans', sans-serif; font-size: 14px;">We're excited to have you on board, and we're here to make procurement effortless.</p>
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
								This message was sent from a notification-only email address. Please do not reply directly to this email. For support or inquiries, contact us at
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