<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<title>Quote Request Submitted Successfully</title>
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
		Thank you! We've received your quote request and will get back to you soon.
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
								Thank You
								<strong style="color:#26683A; font-family: 'Noto Sans', sans-serif; font-size:16px; line-height:25px; margin: 0;">
									{{ $name }}
								</strong>!
							</p>
							<p style="font-size:14px; line-height:25px; font-weight: 500; font-family: 'Noto Sans', sans-serif; margin: 0;">
								Your Quote Request Has Been Submitted Successfully.
							</p>
							<p style="font-size:14px;line-height: 22px;font-family: 'Noto Sans', sans-serif;padding: 0;margin: 8px 0;">
								Our team is reviewing your request and will prepare a customized quote based on the product details and the information you provided. You'll receive a detailed quotation email very soon.
							</p>
							<p style="font-size:14px;line-height: 22px;font-family: 'Noto Sans', sans-serif;padding: 0;margin: 8px 0;">
								If you'd like to track your quote or speak with our support team, feel free to reach out anytime.
							</p>
						</td>
					</tr>

					<tr>
						<td>
							<table cellspacing="0" cellpadding="4" style="font-family: 'Noto Sans', sans-serif; width:100%; font-size:14px; line-height:20px; margin-top:5px;">
								<tr>
									<td style="font-family: 'Noto Sans', sans-serif; vertical-align:top; width:50%; border-right:1px solid #ddd;">
										<h3 style="font-family: 'Noto Sans', sans-serif; font-size:15px; line-height:22px; font-weight: 600; margin:0 0 10px; color: #26683A; text-decoration: underline;">
											Customer Details
										</h3>

										<table>
											<tr>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; font-size: 14px; line-height:22px; color:black;">
													Name
												</td>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; line-height:22px; color:black; font-size: 14px;">
													:
												</td>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; line-height:22px; color:black; font-size: 14px;">
													{{ $name }}
												</td>
											</tr>
											<tr>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; font-size: 15px; line-height:22px; color:black; font-size: 14px;">
													Email
												</td>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; line-height:22px; color:black; font-size: 14px;">
													:
												</td>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; line-height:22px; color:black; font-size: 14px;">
													{{ $email }}
												</td>
											</tr>
											<tr>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; font-size: 15px; line-height:22px; color:black; font-size: 14px;">
													Phone
												</td>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; line-height:22px; color:black; font-size: 14px;">
													:
												</td>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: bold; line-height:22px; color:black; font-size: 14px;">
													{{ $phone }}
												</td>
											</tr>
										</table>
									</td>
									<td style="font-family: 'Noto Sans', sans-serif; vertical-align:top; padding-left:15px;">
										<h3 style="font-family: 'Noto Sans', sans-serif; font-size:15px; line-height:22px; margin:0 0 10px; color: #26683A; font-weight: 600;">
											Delivery Address
										</h3>
										<p style="font-family: 'Noto Sans', sans-serif; margin:0; margin-top: 5px; font-weight: 500; color: #26683A; font-size:14px; line-height:20px;">
											{{ $address }}
										</p>
										<p style="font-family: 'Noto Sans', sans-serif; margin:0; color: #26683A; font-weight: 500; font-size:14px; line-height:20px;">
											{{ $city }}{{ $state ? ', ' . $state : '' }}
										</p>
										<p style="font-family: 'Noto Sans', sans-serif; margin:0; color: #26683A; font-weight: 500; font-size:14px; line-height:20px;">
											{{ $country }}, {{ $zipcode }}
										</p>
									</td>
								</tr>
							</table>

						</td>
					</tr>

					<tr>
						<td>
							<h3 style="font-family: 'Noto Sans', sans-serif; font-size:15px; line-height:22px; font-weight: 600; margin:10px 0 10px; color: #26683A; text-decoration: underline;">
								Product Summary
							</h3>
							<table class="product-table" width="100%" cellspacing="0" cellpadding="8" border="0" style="border-collapse:collapse; font-size:14px; line-height:20px; font-family: 'Noto Sans', sans-serif;">
								<tr style="background:#FAFAFA; font-weight:bold; border-bottom: 1px solid #26683A; font-family: 'Noto Sans', sans-serif; line-height:22px;">
									<td style="font-family: 'Noto Sans', sans-serif; line-height:22px;">
										Image
									</td>
									<td style="font-family: 'Noto Sans', sans-serif; line-height:22px;">
										Product Title
									</td>
									<td align="center" style="font-family: 'Noto Sans', sans-serif; line-height:22px;">
										Model/SKU
									</td>
									<td align="center" style="font-family: 'Noto Sans', sans-serif; line-height:22px;">
										Quantity
									</td>
								</tr>

								<tr>
									<td style="width: 12%">
										<img src="{{ $product->image }}" alt="Product" width="54" height="54" style="display: block; width: 54px; height: 54px; border: 1px solid #DFDFDF; border-radius: 4px; object-fit: cover;">
									</td>
									<td style="width: 50%">
										<strong style="font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px;">{{ Str::limit($product->name, 90, '...') }}</strong>
									</td>
									<td align="center" style="font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px; font-weight: 600; color:#26683A; width:18%;">
										{{ $product->sku }}
									</td>
									<td align="center" style="font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px; width:20%;">
										{{ $product->quantity }}
									</td>
								</tr>
							</table>
						</td>
					</tr>

					@if(!empty($customerNotes))
					<tr>
						<td>
							<h3 style="font-family: 'Noto Sans', sans-serif; font-size:15px; line-height:22px; font-weight: 600; margin:10px 0 10px; color: #26683A; text-decoration: underline;">
								Notes from Customer
							</h3>
							<div style="background:#FAFAFA; padding:12px; border-left:3px solid #26683A; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:22px; color:black;">
								{{ $customerNotes }}
							</div>
						</td>
					</tr>
					@endif

					<tr>
						<td>
							<table width="100%" cellspacing="0" cellpadding="0" border="0">
								<tr>
									<td style="font-size:14px; border-top:3px solid #E2E8F0; padding-top:15px; padding-bottom:5px;  font-family: 'Noto Sans', sans-serif">
										We appreciate your interest in HorecaStore. Our team will reach out with the finalized quote shortly!
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