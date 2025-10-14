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
		Opening a restaurant isn't just a purchase, it's the start of something big!
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
								Thank you for reaching out to <strong style="color:#26683A;">HorecaStore</strong>.
							</p>
							<p style="margin: 0; padding: 5px 0; font-family: 'Noto Sans', sans-serif; line-height: 22px;">
								Opening a restaurant isn't just a purchase, it's the start of something big. We're thrilled to walk beside you as you turn your <strong style="color:#232425;">{{ $restaurantType }}</strong> concept, <strong style="color:#232425;">{{ $companyName }}</strong>, into reality.
							</p>
						</td>
					</tr>

					<!-- Details Section Header -->
					<tr>
						<td align="left" style="padding: 15px 20px 5px; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
							<p style="margin: 0; padding: 0; font-weight: 600; color:#232425; font-family: 'Noto Sans', sans-serif;">
								Here's what you shared:
							</p>
						</td>
					</tr>

					<!-- Details Section with Bullets -->
					<tr>
						<td align="left" style="padding: 5px 20px 15px 40px; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
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
									<strong style="color:#232425; font-family: 'Noto Sans', sans-serif;">Restaurant Concept:</strong>
									<span style="color:#232425; font-family: 'Noto Sans', sans-serif;">{{ $restaurantType }}</span>
								</li>
								<li style="margin-bottom: 0; font-family: 'Noto Sans', sans-serif;">
									<strong style="color:#232425; font-family: 'Noto Sans', sans-serif;">Notes:</strong>
									<span style="color:#232425; font-family: 'Noto Sans', sans-serif;">"{{ $notes }}"</span>
								</li>
							</ul>
						</td>
					</tr>

					<!-- What's Next Section -->
					<tr>
						<td align="left" style="padding: 0 20px; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
							<p style="font-weight: 600; margin: 0; padding: 5px 0; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
								<strong style="color:#232425; font-family: 'Noto Sans', sans-serif;">What Happens Next</strong>
							</p>
							<p style="margin: 0; padding: 5px 0; font-size: 14px; line-height: 22px; font-family: 'Noto Sans', sans-serif;">
								Our restaurant setup team will review your details and prepare the next steps, whether that's a <strong style="color:#232425;">kitchen layout plan</strong>, <strong style="color:#232425;">equipment bundle suggestion</strong>, or <strong style="color:#232425;">financing options</strong> tailored to your concept.
							</p>
							<p style="margin: 0; padding: 5px 0; font-size: 14px; line-height: 22px; font-family: 'Noto Sans', sans-serif;">
								You'll hear from us within <strong style="color:#232425;">24–48 hours</strong>.
							</p>
							<p style="margin: 0; padding: 5px 0 10px; font-size: 14px; line-height: 22px; font-family: 'Noto Sans', sans-serif;">
								If you'd like to share your menu ideas, mood boards, or floor plans, just reply to this email — it goes straight to our restaurant experts.
							</p>
						</td>
					</tr>

					<!-- Welcome Section -->
					<tr>
						<td align="left" style="padding: 10px 20px 20px; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
							<p style="font-weight: 600; line-height: 22px; margin: 0; padding: 0; font-family: 'Noto Sans', sans-serif; font-size:14px; color:#232425;">
								Welcome to the Horeca Family
							</p>
							<p style="margin: 10px 0 0; padding: 0; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height: 22px;">
								We've helped hundreds of first-time owners turn their dreams into thriving restaurants and yours could be next.
							</p>
							<p style="margin: 10px 0 0; padding: 0; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height: 22px; font-weight: 600; color:#26683A;">
								HorecaStore — Where Restaurant Dreams Begin.
							</p>
							<p style="margin: 15px 0 0; padding: 0; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height: 22px;">
								Warm regards,<br/>
								<strong style="color:#232425;">Team HorecaStore</strong>
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