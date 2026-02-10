<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<title>Reset Your Password</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<style>
		@media only screen and (max-width: 600px) {
			.container { width: 100% !important; padding: 20px !important; }
			.reset-button { display: block !important; text-align: center !important; }
			.reset-url { font-size: 11px !important; }
		}
	</style>
</head>
<body style="margin: 0; padding: 0; background: #ffffff; font-family: 'Noto Sans', sans-serif; color: black;">

	<!-- Preheader -->
	<span style="display:none; font-size:1px; color:#ffffff; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden;">
		Reset your HorecaStore account password. This link expires in 10 minutes.
	</span>

	<table width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f8f8f8; font-family:'Noto Sans', sans-serif;">
		<tr>
			<td align="center">
				<table class="container" width="650" cellspacing="0" cellpadding="10" border="0" style="background:#ffffff; border:1px solid #eaeaea; font-family:'Noto Sans', sans-serif;">

					<!-- Logo -->
					<tr>
						<td align="left">
							<img src="{{ $logoUrl }}" alt="Logo" width="120" style="display:block;">
						</td>
					</tr>

					<!-- Main Content -->
					<tr>
						<td>
							<p style="font-size:16px; line-height:25px; font-weight:500; font-family:'Noto Sans', sans-serif; margin:0; color:black;">
								Hi <strong style="color:#26683A;">{{ $name }}</strong>!
							</p>

							<p style="font-size:14px; line-height:22px; font-family:'Noto Sans', sans-serif; margin:8px 0; color:black;">
								We received a request to reset your HorecaStore account password.
							</p>

							<p style="font-size:14px; line-height:22px; font-family:'Noto Sans', sans-serif; font-weight:600; margin:8px 0 15px; color:black;">
								To set a new password, simply click the link below:
							</p>

							<!-- Reset Button -->
							<table cellspacing="0" cellpadding="0" border="0" style="margin:10px 0 20px;">
								<tr>
									<td bgcolor="#26683A" style="border-radius:5px;">
										<a href="{{ $resetUrl }}" class="reset-button" style="background:#26683A; color:#ffffff; padding:12px 24px; text-decoration:none; font-size:14px; line-height:20px; border-radius:5px; display:inline-block; font-family:'Noto Sans', sans-serif;">
											Reset My Password
										</a>
									</td>
								</tr>
							</table>

							<!-- Expiration Notice -->
							<p style="font-size:14px; line-height:22px; font-family:'Noto Sans', sans-serif; color:#666666; margin:0 0 15px; font-style:italic;">
								⏱ This link will expire in <strong>10 minutes</strong> for your security.
							</p>

							<!-- Security Note -->
							<p style="font-size:14px; line-height:22px; font-family:'Noto Sans', sans-serif; margin:0 0 15px; color:black;">
								If you didn't request this, you can safely ignore the email — your current password will remain unchanged. Your privacy and security are important to us.
							</p>
						</td>
					</tr>

					<!-- Divider -->
					<tr>
						<td>
							<table width="100%" cellspacing="0" cellpadding="0" border="0">
								<tr>
									<td style="border-top:1px solid #eaeaea; font-size:0; line-height:0;">&nbsp;</td>
								</tr>
							</table>
						</td>
					</tr>

					<!-- Security Box -->
					<tr>
						<td style="padding:10px 0;">
							<table width="100%" cellspacing="0" cellpadding="0" border="0">
								<tr>
									<td style="background-color:#f0f0f0; border-radius:6px; padding:15px;">
										<p style="margin:0 0 10px; font-size:13px; line-height:20px; font-family:'Noto Sans', sans-serif; font-weight:bold; color:black;">
											Is this link safe?
										</p>
										<p style="margin:0 0 10px; font-size:13px; line-height:20px; font-family:'Noto Sans', sans-serif; color:black;">
											The password reset link in this email starts with: <strong>{{ $frontEndUrl }}</strong>
										</p>
										<p style="margin:0 0 10px; font-size:13px; line-height:20px; font-family:'Noto Sans', sans-serif; color:black;">
											For your security, you can also copy and paste the following URL into your browser:
										</p>
										<p class="reset-url" style="margin:0 0 10px; font-size:12px; line-height:18px; font-family:'Noto Sans', sans-serif; color:black; word-break:break-all; overflow-wrap:break-word;">
											<a href="{{ $resetUrl }}" style="color:#186737; word-break:break-all; font-family:'Noto Sans', sans-serif; font-size:12px;">
												{{ $resetUrl }}
											</a>
										</p>
										<p style="margin:0; font-size:13px; line-height:20px; font-family:'Noto Sans', sans-serif; color:black;">
											If you didn't request a password reset, you can safely ignore this email.
										</p>
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<!-- Footer -->
					<tr>
						<td style="border-top:3px solid #E2E8F0; padding-top:15px; padding-bottom:5px;">
							<p style="font-weight:500; line-height:24px; margin:0; font-family:'Noto Sans', sans-serif; font-size:14px; color:black;">
								Thank you for choosing HorecaStore - where your business gets the best, for less.
							</p>
							<p style="padding:5px 0; color:#26683A; font-weight:500; font-size:14px; margin:0; line-height:20px; font-family:'Noto Sans', sans-serif;">
								&ndash; Team HorecaStore
							</p>
						</td>
					</tr>

				</table>

				<!-- Copyright Footer -->
				<table width="650" cellspacing="0" cellpadding="0" border="0" style="padding:10px; border-top:3px solid #E2E8F0; background-color:rgba(226, 232, 240, 0.3);">
					<tr>
						<td style="padding:10px;">
							<p style="margin:0; font-size:12px; font-family:'Noto Sans', sans-serif; color:black;">
								©{{ now()->year }} {{ $siteUrl }}. All rights reserved. HorecaStore, TheHorecaStore.com, and the HorecaStore.ae logo are trademarks of HorecaStore LLC or its affiliates.
							</p>
							<p style="margin:8px 0 0; font-size:12px; font-family:'Noto Sans', sans-serif; color:black;">
								For support or inquiries, contact us at
								<a href="mailto:{{ $siteEmail }}" style="color:#186737; font-family:'Noto Sans', sans-serif; font-size:12px;">
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