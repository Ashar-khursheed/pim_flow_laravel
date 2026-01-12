<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<title>Quotation Ready - HorecaStore</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<style>
		@media only screen and (max-width: 600px) {
			.container {
				width: 100% !important;
				padding: 20px !important;
			}

			.order-button {
				display: block;
				margin: 15px 0;
			}
		}
	</style>
</head>
@php
	use Illuminate\Support\Str;
@endphp
<body style="margin: 0; padding: 0; background: #ffffff; font-family: 'Noto Sans', sans-serif; color: black;">
	<!-- Preheader text: hidden but visible in email previews -->
	<span style="display: none; font-size: 1px; color: #ffffff; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
		Your HorecaStore quotation is ready to download.
	</span>

	<table width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f8f8f8; font-family: 'Noto Sans', sans-serif;">
		<tr>
			<td align="center">
				<table class="container" width="650" cellspacing="0" cellpadding="10" border="0" style="background:#ffffff; border:1px solid #eaeaea; font-family: 'Noto Sans', sans-serif;">
					<tr>
						<td align="left">
							<img src="{{ $logoUrl }}" alt="Logo" width="120">
						</td>
					</tr>

					<tr>
						<td>
							<p style="font-size:16px; line-height:25px; font-weight: 500; font-family: 'Noto Sans', sans-serif; margin: 0;">
								Hi
								<strong style="color:#26683A; font-family: 'Noto Sans', sans-serif; font-size:16px; line-height:25px; margin: 0;">
									{{ $name }}
								</strong>!
							</p>
							<p style="font-size:14px; line-height:25px; font-weight: 500; font-family: 'Noto Sans', sans-serif; margin: 0;">
								Thank you for generating a quotation from HorecaStore — the smart way to source for restaurants, hotels, and more.
							</p>
						</td>
					</tr>

					<tr>
						<td style="padding-top:10px; font-family: 'Noto Sans', sans-serif;">
							<p style="font-weight: 500; font-family: 'Noto Sans', sans-serif; font-size:15px; line-height:22px; padding: 0; margin: 8px 0;">
								But this isn't just another quote. It's backed by our Dual Promise:
							</p>
							<table cellpadding="0" cellspacing="0" border="0" width="100%" style="font-family: 'Noto Sans', sans-serif; margin-top: 5px;">
								<tr>
									<td valign="top" style="padding-bottom: 8px; font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="20" height="20" style="vertical-align: middle;" />
										<span style="color:#232425; margin-left: 5px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height:20px; font-weight: 500;">Unbeatable Pricing</span>
									</td>
								</tr>
								<tr>
									<td valign="top" style="font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="20" height="20" style="vertical-align: middle;" />
										<span style="color:#232425; margin-left: 5px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height:20px; font-weight: 500;">Stress-Free Sourcing</span>
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<tr>
						<td>
							<p style="font-size:14px;line-height: 22px;font-family: 'Noto Sans', sans-serif;padding: 0;margin: 8px 0;">
								At HorecaStore, we know procurement is more than just comparing numbers. That's why over 5,000+ restaurants, hotels, and catering businesses trust us — not just for products, but for peace of mind.
							</p>
						</td>
					</tr>

					<tr>
						<td style="padding-top:10px; font-family: 'Noto Sans', sans-serif;">
							<p style="font-weight: 600; font-family: 'Noto Sans', sans-serif; font-size:15px; line-height:22px; padding: 0; margin: 8px 0;">
								Beyond the Quote: The HorecaStore Advantage
							</p>
							<table cellpadding="0" cellspacing="0" border="0" width="100%" style="font-family: 'Noto Sans', sans-serif; margin-top: 5px;">
								<tr>
									<td valign="top" style="padding-bottom: 8px; font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="20" height="20" style="vertical-align: middle;" />
										<span style="margin-left: 5px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height:20px;"><strong style="font-weight:600; font-family: 'Noto Sans', sans-serif;">Warranty Included</strong> – Every product, No hidden term</span>
									</td>
								</tr>
								<tr>
									<td valign="top" style="padding-bottom: 8px; font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="20" height="20" style="vertical-align: middle;" />
										<span style="margin-left: 5px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height:20px;"><strong style="font-weight:600; font-family: 'Noto Sans', sans-serif;">Free Shipping, Delivery & Installation</strong> – No lifting, No hassle</span>
									</td>
								</tr>
								<tr>
									<td valign="top" style="padding-bottom: 8px; font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="20" height="20" style="vertical-align: middle;" />
										<span style="margin-left: 5px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height:20px;"><strong style="font-weight:600; font-family: 'Noto Sans', sans-serif;">Returns & Refunds</strong> — Up to 90 Days – Full flexibility, Zero stress</span>
									</td>
								</tr>
								<tr>
									<td valign="top" style="padding-bottom: 8px; font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="20" height="20" style="vertical-align: middle;" />
										<span style="margin-left: 5px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height:20px;"><strong style="font-weight:600; font-family: 'Noto Sans', sans-serif;">Sourcing, Simplified</strong> – One Supplier. One Invoice.</span>
									</td>
								</tr>
								<tr>
									<td valign="top" style="font-family: 'Noto Sans', sans-serif;">
										<img src="{{ $rightPngURL }}" alt="right" width="20" height="20" style="vertical-align: middle;" />
										<span style="margin-left: 5px; font-family: 'Noto Sans', sans-serif; font-size: 14px; line-height:20px;"><strong style="font-weight:600; font-family: 'Noto Sans', sans-serif;">24/7 Customer Support</strong> – Because service truly matters</span>
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<tr>
						<td style="padding-top:10px; font-family: 'Noto Sans', sans-serif;">
							<p style="font-weight: 600; font-family: 'Noto Sans', sans-serif; font-size:15px; line-height:22px; padding: 0; margin: 8px 0;">
								Your Quotation is Ready
							</p>
						</td>
					</tr>

					<tr>
						<td>
							<a href="{{ $downloadLink }}" class="order-button" style="background:#26683A; color:#fff; padding:12px 24px; margin-top: 10px; font-size:14px; line-height:20px; text-decoration:none; border-radius:5px; display:inline-block; font-family: 'Noto Sans', sans-serif;">
								Download Now
							</a>
						</td>
					</tr>

					<tr>
						<td>
							<p style="font-size:14px;line-height: 22px;font-family: 'Noto Sans', sans-serif;padding: 0;margin: 8px 0;">
								Ready to place your order?
								<a href="{{ $orderLink }}" style="color:#26683A; font-weight: 600; text-decoration: underline; font-family: 'Noto Sans', sans-serif;">Place Your Order</a>
							</p>
						</td>
					</tr>

					<tr>
						<td>
							<table width="100%" cellspacing="0" cellpadding="0" border="0">
								<tr>
									<td style="font-size:14px; border-top:3px solid #E2E8F0; padding-top:15px; padding-bottom:5px;  font-family: 'Noto Sans', sans-serif">
										Behind Every Great Plate, There's HorecaStore
									</td>
								</tr>
								<tr>
									<td style="font-weight: 500; line-height: 24px; margin: 0; padding: 0; font-family: 'Noto Sans', sans-serif; font-size:14px;">
										Thank you for choosing HorecaStore - where your business gets the best, for less.
									</td>
								</tr>
								<tr>
									<td style="font-size:14px; color: #26683A; font-weight: 500; line-height:22px; font-family: 'Noto Sans', sans-serif">
										<p style="padding: 5px 0; color: #26683A; font-weight: 500; font-size: 14px; margin: 0; line-height: 20px; font-family: 'Noto Sans', sans-serif;">
											&ndash; Team HorecaStore
										</p>
									</td>
								</tr>
							</table>
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