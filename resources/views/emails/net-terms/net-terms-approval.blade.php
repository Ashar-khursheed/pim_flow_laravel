<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<title>Your Net Terms Application Has Been Approved</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<style>
		@media only screen and (max-width: 600px) {
			.container {
				width: 100% !important;
				padding: 20px !important;
			}
		}
	</style>
</head>

<body style="margin: 0; padding: 0; background: #ffffff; font-family: 'Noto Sans', sans-serif; color: #232425;">
	<span style="display: none; font-size: 1px; color: #ffffff; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
		Your Net Payment Terms application has been approved. Check out without paying upfront.
	</span>

	<table width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#ffffff; font-family: 'Noto Sans', sans-serif;">
		<tr>
			<td align="center" style="padding: 20px; font-family: 'Noto Sans', sans-serif;">
				<table class="container" width="600" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #eaeaea; font-family: 'Noto Sans', sans-serif;">
					<!-- Logo Section -->
					<tr>
						<td align="left" style="padding: 20px; font-family: 'Noto Sans', sans-serif;">
							<img src="{{ $logoUrl }}" alt="Logo" width="120" style="display: block;">
						</td>
					</tr>

					<!-- Greeting Section -->
					<tr>
						<td style="padding: 0 20px; font-size: 16px; font-family: 'Noto Sans', sans-serif;">
							<p style="margin: 0; padding: 5px 0; font-family: 'Noto Sans', sans-serif;">
								Hi <strong style="color:#26683A; font-family: 'Noto Sans', sans-serif;">{{ $name }}</strong>,
							</p>
						</td>
					</tr>

					<!-- Main Content Section -->
					<tr>
						<td style="padding: 0px 20px; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
							<p style="margin: 0; padding: 5px 0; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
								We're excited to let you know that your application for <strong>Net Payment Terms</strong> with The Horeca Store has been <strong>approved</strong>!
							</p>
						</td>
					</tr>

					<!-- Approved Credit Details Section -->
					<tr>
						<td style="padding: 0 20px; font-size: 15px; font-family: 'Noto Sans', sans-serif;">
							<p style="font-weight: bold; margin: 0; padding: 10px 0; font-family: 'Noto Sans', sans-serif;">
								Your Approved Credit Details:
							</p>
							<table cellspacing="0" cellpadding="0" border="0" style="width: 100%; font-family: 'Noto Sans', sans-serif;">
								<tr>
									<td valign="top" style="color:#26683A; padding-bottom: 8px; font-family: 'Noto Sans', sans-serif;">
										<span style="color: #26683A; font-size: 16px; vertical-align: middle;">✓</span>
										<span style="color:#232425; margin-left: 5px; font-family: 'Noto Sans', sans-serif; font-size: 14px;">
											<strong>Approved Credit Limit:</strong>{{ $currency }} {{ number_format($approvedAmount, 2, '.', ',') }}
										</span>
									</td>
								</tr>
								<tr>
									<td valign="top" style="color:#26683A; font-family: 'Noto Sans', sans-serif;">
										<span style="color: #26683A; font-size: 16px; vertical-align: middle;">✓</span>
										<span style="color:#232425; margin-left: 5px; font-family: 'Noto Sans', sans-serif; font-size: 14px;">
											<strong>Payment Terms:</strong> {{ $termSelection }}
										</span>
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<!-- How to Use Section -->
					<tr>
						<td style="padding: 0px 20px; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
							<p style="margin: 0; padding: 5px 0; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
								Now, you can <strong>check out without paying upfront</strong>. Just select <strong>"Use Net Payment Terms"</strong> during checkout and enjoy a smoother, hassle-free shopping experience.
							</p>
							<p style="margin: 0; padding: 5px 0; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
								You can also view your Net Terms details anytime in your account: <strong>Profile → Net Payment Terms</strong>.
							</p>
						</td>
					</tr>

					<!-- Contact Section -->
					<tr>
						<td style="padding: 10px 20px; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
							<p style="font-weight: bold; font-size: 14px; padding: 5px 0; margin: 0; font-family: 'Noto Sans', sans-serif;">
								Need help or have questions?
							</p>
							<table cellspacing="0" cellpadding="0" border="0" style="width: 100%; font-family: 'Noto Sans', sans-serif;">
								<tr>
									<td style="vertical-align: middle; width: 30px;">
										<span style="font-size: 20px;">✉️</span>
									</td>
									<td style="padding: 5px 0;">
										<span style="font-size:14px; line-height:22px; font-family: 'Noto Sans', sans-serif;">
											<strong>Email us at:</strong>
											<a href="mailto:{{ $siteEmail }}" style="color:#26683A; text-decoration: none; font-weight: 600;">
												{{ $siteEmail }}
											</a>
										</span>
									</td>
								</tr>
								<tr>
									<td style="vertical-align: middle; width: 30px;">
										<span style="font-size: 20px;">📞</span>
									</td>
									<td style="padding: 5px 0;">
										<span style="font-size:14px; line-height:22px; font-family: 'Noto Sans', sans-serif;">
											<strong>Or call us anytime at</strong>
											<a href="tel:{{ $phoneNumber }}" style="color:#26683A; text-decoration: none; font-weight: 600;">
												{{ $phoneNumber }}
											</a>
										</span>
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<!-- Closing Section -->
					<tr>
						<td style="padding: 10px 20px; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
							<p style="font-weight: 500; line-height: 24px; margin: 0; padding: 0; font-family: 'Noto Sans', sans-serif; font-size:14px;">
								We're thrilled to support your business and make managing your orders easier.
							</p>
							<p style="color: #232425; font-weight: 500; margin: 0; padding: 0; font-family: 'Noto Sans', sans-serif; font-size:14px;">
								– Team HorecaStore
							</p>
						</td>
					</tr>
				</table>

				<!-- Footer Section -->
				<table width="650" cellspacing="0" cellpadding="0" border="0" style="padding:10px; border-top:3px solid #E2E8F0; background-color: rgba(226, 232, 240, 0.3); font-size:11px; color:#3F3F3F;">
					<tr>
						<td>
							<p style="margin: 0; font-size:12px; font-family: 'Noto Sans', sans-serif;">
								©{{ now()->year }} {{ $siteUrl }}. All rights reserved. HorecaStore, TheHorecaStore.com, and the HorecaStore.ae logo are trademarks of HorecaStore LLC or its affiliates.
							</p>
							<p style="margin: 8px 0 0; font-size:12px; font-family: 'Noto Sans', sans-serif;">
								For support or inquiries, contact us at
								<a href="mailto:{{ $siteEmail }}" style="color:#186737; font-family: 'Noto Sans', sans-serif; font-size:12px;">
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