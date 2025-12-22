<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<title>Reset Your Password</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<style>
		@media only screen and (max-width: 600px) {
			.container {
				width: 100% !important;
				padding: 20px !important;
			}
			.reset-button {
				width: 100% !important;
			}
			.footer-box {
				font-size: 12px !important;
				padding: 10px !important;
			}
		}
	</style>
</head>
<body style="margin: 0; padding: 0; background: #ffffff; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;">
	<div style="width: 100%; padding: 20px; box-sizing: border-box; background-color: #f8f8f8;">
		<div class="container" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 40px; border: 1px solid #eaeaea; box-sizing: border-box;">
			<!-- Logo -->
			<div style="margin-bottom: 20px;">
				<img src="{{ $logoUrl }}" alt="Logo" style="width: 120px;" />
			</div>
			<!-- Greeting -->
			<p style="font-size: 16px; color: #000000;">Hi <strong style="color: #186737;">{{ $name }}</strong>!</p>
			<p style="font-size: 15px; color: #000000;">We received a request to reset your HorecaStore account password.</p>
			<p style="font-size: 15px; color: #000000; font-weight: 600;">To set a new password, simply click the link below:</p>
			<!-- Reset Button -->
			<div class="reset-button" style="margin: 25px 0;">
				<a href="{{ $resetUrl }}" style="background-color: #186737; color: #ffffff; padding: 12px 24px; text-decoration: none; font-size: 14px; border-radius: 5px; display: inline-block;">
					Reset My Password
				</a>
			</div>
			<!-- Expiration Notice -->
			<p style="font-size: 15px; color: #666666; margin: 10px 0 20px; font-style: italic;">
				⏱ This link will expire in <strong>10 minutes</strong> for your security.
			</p>
			<!-- Security Note -->
			<p style="font-size: 15px; color: #000000;">
				If you didn't request this, you can safely ignore the email — your current password will remain unchanged. Your privacy and security are important to us.
			</p>
			<p style="color: #186737; font-weight: bold;">– Team HorecaStore</p>
			<!-- Divider -->
			<div style="border-top: 1px solid #eaeaea; margin: 30px 0;"></div>
			<!-- Security Box -->
			<div class="footer-box" style="font-size: 13px; color: #333333; background-color: #f0f0f0; padding: 15px; border-radius: 6px;">
				<p style="margin: 0 0 10px;"><strong>Is this link safe?</strong></p>
				<p style="margin: 0 0 10px;">The password reset link in this email starts with: <strong>{{ $frontEndUrl }}</strong></p>
				<p style="margin: 0 0 10px;">For your security, you can also copy and paste the following URL into your browser:</p>
				<p style="margin: 0 0 10px; word-break: break-all; overflow-wrap: break-word;">
					<a href="{{ $resetUrl }}" style="color: #186737; word-break: break-all;">{{ $resetUrl }}</a>
				</p>
				<p style="margin: 0;">If you didn't request a password reset, you can safely ignore this email.</p>
			</div>
		</div>
	</div>
</body>
</html>