<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<title>Restaurant Quote Request - HorecaStore</title>
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
		We've received your restaurant quote request and our team is excited to help!
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
						<td align="left" style="padding: 0 20px; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
							<p style="margin: 0; padding: 5px 0; font-family: 'Noto Sans', sans-serif; line-height: 22px;">
								Hi <strong style="color:#26683A; font-family: 'Noto Sans', sans-serif;">{{ $name }}</strong>,
							</p>
							<p style="margin: 0; padding: 5px 0; font-family: 'Noto Sans', sans-serif; line-height: 22px;">
								Thank you for getting in touch with <strong style="color:#26683A;">HorecaStore</strong>. We’ve received your request and our team is excited to connect with you about your restaurant plans.
							</p>
							<p style="margin: 0; padding: 5px 0; font-family: 'Noto Sans', sans-serif; line-height: 22px;">
								<strong style="color:#232425; font-family: 'Noto Sans', sans-serif;">Here are the details you shared:</strong>
							</p>
						</td>
					</tr>

					<!-- Details Section with Bullets -->
					<tr>
						<td align="left" style="padding: 10px 20px 15px 40px; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
							<ul style="margin: 0; padding: 0; list-style-position: outside; font-family: 'Noto Sans', sans-serif;">
								<li style="margin-bottom: 8px; font-family: 'Noto Sans', sans-serif;">
									<strong style="color:#232425; font-family: 'Noto Sans', sans-serif;">Name:</strong>
									<span style="color:#232425; font-family: 'Noto Sans', sans-serif;">{{ $name }}</span>
								</li>
								<li style="margin-bottom: 8px; font-family: 'Noto Sans', sans-serif;">
									<strong style="color:#232425; font-family: 'Noto Sans', sans-serif;">Phone:</strong>
									<span style="color:#232425; font-family: 'Noto Sans', sans-serif;">{{ $phone }}</span>
								</li>
								<li style="margin-bottom: 8px; font-family: 'Noto Sans', sans-serif;">
									<strong style="color:#232425; font-family: 'Noto Sans', sans-serif;">Email:</strong>
									<span style="color:#232425; font-family: 'Noto Sans', sans-serif;">{{ $email }}</span>
								</li>
								<li style="margin-bottom: 8px; font-family: 'Noto Sans', sans-serif;">
									<strong style="color:#232425; font-family: 'Noto Sans', sans-serif;">Company / Restaurant Name:</strong>
									<span style="color:#232425; font-family: 'Noto Sans', sans-serif;">{{ $companyName }}</span>
								</li>
								<li style="margin-bottom: 8px; font-family: 'Noto Sans', sans-serif;">
									<strong style="color:#232425; font-family: 'Noto Sans', sans-serif;">Restaurant Type / Concept:</strong>
									<span style="color:#232425; font-family: 'Noto Sans', sans-serif;">{{ $restaurantType }}</span>
								</li>
								<li style="margin-bottom: 0; font-family: 'Noto Sans', sans-serif;">
									<strong style="color:#232425; font-family: 'Noto Sans', sans-serif;">Notes:</strong>
									<span style="color:#232425; font-family: 'Noto Sans', sans-serif;">{{ $notes }}</span>
								</li>
							</ul>
						</td>
					</tr>

					<!-- What's Next Section -->
					<tr>
						<td align="left" style="padding: 0 20px; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
							<p style="font-weight: 600; margin: 0; padding: 5px 0; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
								<strong style="color:#232425; font-family: 'Noto Sans', sans-serif;">What Happens Next?</strong>
							</p>
							<p style="margin: 0; padding: 5px 0; font-size: 14px; line-height: 22px; font-family: 'Noto Sans', sans-serif;">
								Our <strong style="color:#232425;">HORECA experts</strong> will review your information and follow up with the <strong style="color:#232425;">next steps</strong>. This may include a tailored quote, guidance on setup, or answers to any specific questions you raised. You can expect a response within <strong style="color:#232425;">24–48 hours</strong>.
							</p>
							<p style="margin: 0; padding: 5px 0 10px; font-size: 14px; line-height: 22px; font-family: 'Noto Sans', sans-serif;">
								If you’d like to share any additional documents, floor plans, or requirements, simply reply to this email — it will reach our team directly.
							</p>
						</td>
					</tr>

					<!-- Closing Message -->
					<tr>
						<td align="left" style="padding: 10px 20px 20px; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
							<p style="font-weight: 600; line-height: 22px; margin: 0; padding: 0; font-family: 'Noto Sans', sans-serif; font-size:14px; color:#232425;">
								We’re looking forward to supporting you on your <strong style="color:#232425;">restaurant journey</strong>.
							</p>
							<p style="margin: 10px 0 0; padding: 0; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height: 22px;">
								Best regards,<br/>
								<strong style="color:#232425;">- Team HorecaStore</strong>
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
