<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<title>Quote Request Submitted Successfully</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<style>
		@media only screen and (max-width: 600px) {
			.container {
				width: 100% !important;
				padding: 20px !important;
			}

			.customer-details-table {
				display: block !important;
			}

			.customer-details-table td {
				display: block !important;
				width: 100% !important;
				border-right: none !important;
				border-bottom: 1px solid #ddd;
				padding-bottom: 15px !important;
				margin-bottom: 15px !important;
			}
		}
	</style>
</head>

@php
	use Illuminate\Support\Str;
@endphp

<body style="margin: 0; padding: 0; background: #ffffff; font-family: 'Noto Sans', sans-serif; color: #232425;">
	<!-- Preheader text: hidden but visible in email previews -->
	<span style="display: none; font-size: 1px; color: #ffffff; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
		Thank you! We've received your quote request and will get back to you soon.
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
								Thank You
								<strong style="color:#26683A; font-family: 'Noto Sans', sans-serif;">{{ $name }}</strong>!
							</p>
						</td>
					</tr>

					<!-- Main Content Section -->
					<tr>
						<td style="padding: 0px 20px; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
							<p style="font-weight: 600; margin: 0; padding: 5px 0; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
								Your Quote Request Has Been Submitted Successfully.
							</p>
							<p style="margin: 0; padding: 5px 0; font-size: 14px; line-height: 22px; font-family: 'Noto Sans', sans-serif;">
								Our team is reviewing your request and will prepare a customized quote based on the product details and the information you provided. You'll receive a detailed quotation email very soon.
							</p>
							<p style="margin: 0; padding: 5px 0; font-size: 14px; line-height: 22px; font-family: 'Noto Sans', sans-serif;">
								If you'd like to track your quote or speak with our support team, feel free to reach out anytime.
							</p>
						</td>
					</tr>

					<!-- Customer Details & Address Section -->
					<tr>
						<td style="padding: 15px 20px; font-family: 'Noto Sans', sans-serif;">
							<table class="customer-details-table" cellspacing="0" cellpadding="4" style="font-family: 'Noto Sans', sans-serif; width:100%; font-size:14px; line-height:20px;">
								<tr>
									<td style="font-family: 'Noto Sans', sans-serif; vertical-align:top; width:50%; border-right:1px solid #ddd; padding-right:15px;">
										<h3 style="font-family: 'Noto Sans', sans-serif; font-size:15px; line-height:22px; font-weight: 600; margin:0 0 10px; color: #26683A; text-decoration: underline;">
											Customer Details
										</h3>
										<table cellspacing="0" cellpadding="3" style="width:100%;">
											<tr>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; font-size: 14px; line-height:22px; color:#232425; width:60px;">
													Name
												</td>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; line-height:22px; color:#232425; font-size: 14px; width:10px;">
													:
												</td>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; line-height:22px; color:#232425; font-size: 14px;">
													{{ $name }}
												</td>
											</tr>
											<tr>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; font-size: 14px; line-height:22px; color:#232425;">
													Email
												</td>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; line-height:22px; color:#232425; font-size: 14px;">
													:
												</td>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; line-height:22px; color:#232425; font-size: 14px;">
													{{ $email }}
												</td>
											</tr>
											<tr>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; font-size: 14px; line-height:22px; color:#232425;">
													Phone
												</td>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 500; line-height:22px; color:#232425; font-size: 14px;">
													:
												</td>
												<td style="font-family: 'Noto Sans', sans-serif; font-weight: 600; line-height:22px; color:#232425; font-size: 14px;">
													{{ $phone }}
												</td>
											</tr>
										</table>
									</td>
									<td style="font-family: 'Noto Sans', sans-serif; vertical-align:top; padding-left:15px;">
										<h3 style="font-family: 'Noto Sans', sans-serif; font-size:15px; line-height:22px; margin:0 0 10px; color: #26683A; font-weight: 600; text-decoration: underline;">
											Delivery Address
										</h3>
										<p style="font-family: 'Noto Sans', sans-serif; margin:0; margin-top: 5px; font-weight: 500; color: #232425; font-size:14px; line-height:20px;">
											{{ $address }}
										</p>
										<p style="font-family: 'Noto Sans', sans-serif; margin:0; color: #232425; font-weight: 500; font-size:14px; line-height:20px;">
											{{ $city }}{{ $state ? ', ' . $state : '' }}
										</p>
										<p style="font-family: 'Noto Sans', sans-serif; margin:0; color: #232425; font-weight: 500; font-size:14px; line-height:20px;">
											{{ $country }}, {{ $zipcode }}
										</p>
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<!-- Product Summary Section -->
					<tr>
						<td style="padding: 15px 20px; font-family: 'Noto Sans', sans-serif;">
							<h3 style="font-family: 'Noto Sans', sans-serif; font-size:15px; line-height:22px; font-weight: 600; margin:0 0 10px; color: #26683A; text-decoration: underline;">
								Product Summary
							</h3>
							<table class="product-table" width="100%" cellspacing="0" cellpadding="8" border="0" style="border-collapse:collapse; font-size:14px; line-height:20px; font-family: 'Noto Sans', sans-serif; border:1px solid #eaeaea;">
								<thead>
									<tr style="background:#FAFAFA; font-weight:600; border-bottom: 2px solid #26683A; font-family: 'Noto Sans', sans-serif; line-height:22px;">
										<td style="font-family: 'Noto Sans', sans-serif; line-height:22px; padding: 10px 8px;">
											Image
										</td>
										<td style="font-family: 'Noto Sans', sans-serif; line-height:22px; padding: 10px 8px;">
											Product Title
										</td>
										<td align="center" style="font-family: 'Noto Sans', sans-serif; line-height:22px; padding: 10px 8px;">
											Model/SKU
										</td>
										<td align="center" style="font-family: 'Noto Sans', sans-serif; line-height:22px; padding: 10px 8px;">
											Quantity
										</td>
									</tr>
								</thead>
								<tbody>
									<tr style="border-bottom: 1px solid #eaeaea;">
										<td style="width: 80px; padding: 10px 8px;">
											<img src="{{ $product->image }}" alt="Product" width="60" height="60" style="display: block; width: 60px; height: 60px; border: 1px solid #DFDFDF; border-radius: 4px; object-fit: cover;">
										</td>
										<td style="padding: 10px 8px; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px; font-weight: 500; color:#232425;">
											{{ Str::limit($product->name, 90, '...') }}
										</td>
										<td align="center" style="font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px; font-weight: 600; color:#26683A; padding: 10px 8px;">
											{{ $product->sku }}
										</td>
										<td align="center" style="font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:20px; font-weight: 600; color:#232425; padding: 10px 8px;">
											{{ $product->quantity }}
										</td>
									</tr>
								</tbody>
							</table>
						</td>
					</tr>

					<!-- Customer Notes Section (if exists) -->
					@if(!empty($customerNotes))
					<tr>
						<td style="padding: 15px 20px; font-family: 'Noto Sans', sans-serif;">
							<h3 style="font-family: 'Noto Sans', sans-serif; font-size:15px; line-height:22px; font-weight: 600; margin:0 0 10px; color: #26683A; text-decoration: underline;">
								Notes from Customer
							</h3>
							<div style="background:#FAFAFA; padding:12px; border-left:3px solid #26683A; font-family: 'Noto Sans', sans-serif; font-size:14px; line-height:22px; color:#232425;">
								{{ $customerNotes }}
							</div>
						</td>
					</tr>
					@endif

					<!-- Closing Section -->
					<tr>
						<td style="padding: 10px 20px; font-size: 14px; font-family: 'Noto Sans', sans-serif;">
							<p style="font-weight: 500; line-height: 24px; margin: 0; padding: 0; font-family: 'Noto Sans', sans-serif; font-size:14px;">
								We appreciate your interest in HorecaStore — where your business gets the best, for less.
							</p>
							<p style="font-weight: 500; line-height: 24px; margin: 0; padding: 0; font-family: 'Noto Sans', sans-serif; font-size:14px;">
								Our team will reach out with the finalized quote shortly!
							</p>
							<p style="color: #232425; font-weight: 500; margin: 0; padding: 5px 0 0 0; font-family: 'Noto Sans', sans-serif; font-size:14px;">
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