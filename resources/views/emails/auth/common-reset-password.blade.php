<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<title>Reset Your Password</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
</head>

<body style="margin: 0; padding: 0; background: #ffffff; font-family: 'Noto Sans', sans-serif;">
	<table cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8f8f8;">
		<tr>
			<td>
				<table cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #eaeaea; padding: 20px; box-sizing: border-box; font-family: 'Noto Sans', sans-serif;">

					<!-- Logo -->
					<tr>
						<td style="padding-bottom: 20px; font-family: 'Noto Sans', sans-serif;">
							<img src="{{ $logoUrl }}" alt="Logo" style="width: 120px;" />
						</td>
					</tr>

					<!-- Greeting -->
					<tr>
						<td style="font-size: 16px; color: #000000; padding-bottom: 15px; font-family: 'Noto Sans', sans-serif;">
							Hi <strong style="color: #186737;">{{ $name }}</strong>,
						</td>
					</tr>

					<!-- Intro Message -->
					<tr>
						<td style="font-size: 15px; color: #000000; padding-bottom: 10px; font-family: 'Noto Sans', sans-serif;">
							We’ve recently strengthened our security systems, including enhanced DNS protection, to keep your account safe.
						</td>
					</tr>

					<tr>
						<td style="font-size: 15px; color: #000000; padding-bottom: 20px; font-weight: 600; font-family: 'Noto Sans', sans-serif;">
							As part of this update, we’ve automatically reset your password. To continue accessing your account, please create a new password:
						</td>
					</tr>

					<tr>
						<td style="font-family: 'Noto Sans', sans-serif;">
							<a href="{{ $resetUrl }}" style="background-color: #186737; color: #ffffff; padding: 12px 24px; text-decoration: none; font-size: 14px; font-weight: 600; border-radius: 5px; display: inline-block;">
								Reset My Password
							</a>
							<p style="font-size: 13px; margin-top: 10px; color: #444444; font-family: 'Noto Sans', sans-serif;">
								(This secure link will expire in 7 days for your account protection.)
							</p>
						</td>
					</tr>

					<!-- Alternative URL -->
					<tr>
						<td style="font-size: 14px; color: #000000; font-weight: 600; padding-top: 10px; font-family: 'Noto Sans', sans-serif;">
							Or copy and paste this URL into your browser:
						</td>
					</tr>

					<tr>
						<td style="font-size: 14px; color: #186737; padding: 10px 0; font-family: 'Noto Sans', sans-serif;">
							<a href="{{ $resetUrl }}" style="color: #186737; text-decoration: none;">{{ $resetUrl }}</a>
						</td>
					</tr>

					<!-- Divider -->


					<!-- Security Message -->
					<tr>
						<td style="font-size: 13px; color: #333333;">
							<p style="margin: 0 0 10px; font-size: 13px; font-family: 'Noto Sans', sans-serif;">
								<strong>How to verify this email is safe</strong>
							</p>
							<ul style="margin: 0;">
								<li style="margin: 0 0 5px; font-size: 13px; font-family: 'Noto Sans', sans-serif;">
									Our official secure links always begin with:
								</li>
								<p style="margin: 0 0 5px;">
									<strong>{{ $frontEndUrl }}</strong>
								</p>
							</ul>
						</td>
					</tr>
					<!-- Signature -->
					<tr>
						<td style="padding-top: 10px; font-size: 14px; color: #000000; font-family: 'Noto Sans', sans-serif;">
							Stay secure,
						</td>
					</tr>
					<tr>
						<td style="padding-top:0px; font-size: 14px; color: #186737; font-family: 'Noto Sans', sans-serif;">
							– Team HorecaStore
						</td>
					</tr>

				</table>
			</td>

		</tr>
		<tr>
			<td>
				<table width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; padding:10px; border-top:3px solid #E2E8F0;  margin: 0 auto; background-color: rgba(226, 232, 240, 0.3); font-size:11px; color:#3F3F3F;">
					<tr>
						<td>
							<p style="font-size: 12px;  font-family: 'Noto Sans', sans-serif">
								©{{ date('Y') }} {{ $siteUrl }}. All rights reserved. HorecaStore, TheHorecaStore.com, and the HorecaStore.ae logo are trademarks of HorecaStore LLC or its affiliates.
							</p>
							<p style="font-size: 12px; font-family: 'Noto Sans', sans-serif">
								This message was sent from a notification-only address. Please do not reply directly to this email. For support or inquiries, contact us at {{ $siteEmail }}
							</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>