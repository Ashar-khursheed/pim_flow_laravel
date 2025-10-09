<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<title>Support Ticket Created - HorecaStore</title>
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
	<!-- Preheader text -->
	<span style="display: none; font-size: 1px; color: #ffffff; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden; font-family: 'Noto Sans', sans-serif;">
		Your support ticket has been created. Our team is reviewing your request.
	</span>

	<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #ffffff; font-family: 'Noto Sans', sans-serif;">
		<tr>
			<td align="center" style="padding: 20px; font-family: 'Noto Sans', sans-serif;">
				<table border="0" cellpadding="0" cellspacing="0" width="600" style="border: 1px solid #eaeaea; font-family: 'Noto Sans', sans-serif;">
					<!-- Logo -->
					<tr>
						<td align="left" style="padding: 20px; font-family: 'Noto Sans', sans-serif;">
							<img src="{{ $logoUrl }}" alt="HorecaStore Logo" width="120" style="display: block;" />
						</td>
					</tr>

					<!-- Greeting -->
					<tr>
						<td align="left" style="padding: 0 20px; font-size: 16px; font-family: 'Noto Sans', sans-serif;">
							<p style="margin: 0; padding: 5px 0; font-family: 'Noto Sans', sans-serif;">Hi <strong style="color:#26683A; font-family: 'Noto Sans', sans-serif;">{{ $name }}</strong>,</p>
							<p style="margin: 0; padding: 5px 0; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 22px;">
								Thank you for reaching out to <strong style="color:#26683A;">HorecaStore</strong>.
							</p>
							<p style="margin: 0; padding: 5px 0; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height: 22px;">
								Your support ticket has been successfully created. Our team is reviewing your request and will get back to you as soon as possible.
							</p>
						</td>
					</tr>

					<!-- Ticket Details Section -->
					<tr>
						<td align="left" style="padding: 15px 20px; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
							<p style="font-weight: 600; margin: 0; padding: 5px 0; font-size: 15px; font-family: 'Noto Sans', sans-serif;">
								📋 Ticket Details:
							</p>
							<table cellpadding="4" cellspacing="0" border="0" width="100%" style="margin-top: 10px; font-family: 'Noto Sans', sans-serif;">
								<tr>
									<td style="padding: 4px 0; font-family: 'Noto Sans', sans-serif;">
										<strong style="color:#26683A; font-family: 'Noto Sans', sans-serif;">Ticket Number:</strong>
										<span style="color:#232425; font-family: 'Noto Sans', sans-serif;">{{ $ticketNumber }}</span>
									</td>
								</tr>
								<tr>
									<td style="padding: 4px 0; font-family: 'Noto Sans', sans-serif;">
										<strong style="color:#26683A; font-family: 'Noto Sans', sans-serif;">Submitted On:</strong>
										<span style="color:#232425; font-family: 'Noto Sans', sans-serif;">{{ $ticketDate }}</span>
									</td>
								</tr>
								<tr>
									<td style="padding: 4px 0; font-family: 'Noto Sans', sans-serif;">
										<strong style="color:#26683A; font-family: 'Noto Sans', sans-serif;">Subject:</strong>
										<span style="color:#232425; font-family: 'Noto Sans', sans-serif;">{{ $subject }}</span>
									</td>
								</tr>
								<tr>
									<td style="padding: 4px 0; font-family: 'Noto Sans', sans-serif;">
										<strong style="color:#26683A; font-family: 'Noto Sans', sans-serif;">Description:</strong>
										<span style="color:#232425; font-family: 'Noto Sans', sans-serif;">{{ $description }}</span>
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<!-- What's Next Section -->
					<tr>
						<td align="left" style="padding: 0 20px; font-size: 15px; font-family: 'Noto Sans', sans-serif;">
							<p style="font-weight: 600; margin: 0; padding: 10px 0; font-size: 15px; font-family: 'Noto Sans', sans-serif;">What's Next?</p>
							<p style="margin: 0; padding: 5px 0; font-size: 14px; line-height: 22px; font-family: 'Noto Sans', sans-serif;">
								Our support team will review your request and respond within 24 to 48 hours. You'll receive an email update once there's progress on your ticket.
							</p>
							<p style="margin: 0; padding: 5px 0 10px; font-size: 14px; line-height: 22px; font-family: 'Noto Sans', sans-serif;">
								If you need to add more information, simply reply to this email — it will automatically update your ticket.
							</p>
						</td>
					</tr>

					<!-- Closing Message -->
					<tr>
						<td align="left" style="padding: 10px 20px; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
							<p style="font-weight: 600; line-height: 24px; margin: 0; padding: 5px 0; font-family: 'Noto Sans', sans-serif; font-size:15px; color:#26683A;">
								Thank you for choosing HorecaStore!
							</p>
							<p style="margin: 10px 0 0; padding: 0; font-family: 'Noto Sans', sans-serif; font-size:14px;">
								Best regards,<br/>
								<strong style="color:#26683A;">- Team HorecaStore</strong>
							</p>
						</td>
					</tr>
				</table>

				<!-- Footer -->
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
