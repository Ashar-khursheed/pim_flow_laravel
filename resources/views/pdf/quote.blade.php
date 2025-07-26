<!DOCTYPE html>

<html lang="en">

<head>
	<meta charset="utf-8" />
	<meta content="width=device-width, initial-scale=1.0" name="viewport" />
	<title>Sales Quotation Design</title>
	<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet" />
	<script src="https://cdn.tailwindcss.com"></script>
</head>
@php
use Illuminate\Support\Str;
@endphp
<body>
	<div id="targetDiv" style="width: auto; min-height: 290mm; margin: 0px auto;  font-size: 12px; line-height: 1.3; font-family: Outfit;background-color: white;">
		<div style="min-height: 1070px; height: 1070px; display: flex; flex-direction: column; padding: 50px; box-sizing: border-box;background-color: white;">
			<table style="width: 100%; border-collapse: collapse; margin-bottom: 1rem; font-size: 14px;">
				<tr>
					<td style="width: 33.33%; padding: 0.5rem; vertical-align: top;">
						<img alt="logo" src="{{ $logoUrl }}" style="height: 7.5rem; width: 10rem;" />
					</td>

					<td style="width: 33.33%; text-align: center; padding: 0.5rem; vertical-align: top;">
						<h1 style="font-size: 16px; font-weight: 700; margin: 0;">SALES QUOTATION</h1>
						<p style="font-size: 13px; font-weight: 700; color: #186737; margin: 4px 0 0;">Best Price. Zero Hassle.</p>
					</td>

					<!-- Contact Info Column -->
					<td style="width: 33.33%; text-align: right; font-size: 11px; padding: 0.5rem; vertical-align: top;">
						<p style="font-weight: 700; margin: 0;">{{ $companyName }}.</p>
						<p style="margin: 0;">{{ $street }}</p>
						<p style="margin: 0;">{{ $city }}</p>
						<p style="margin: 0;">Phone: {{ $phone }}</p>
						<p style="margin: 0;">Email: {{ $siteEmail }}</p>
						<p style="margin: 0;">{{ $siteURL }}</p>
					</td>
				</tr>
			</table>

			<table style="width: 100%; border-collapse: collapse; margin-bottom: 1rem; font-size: 14px;">
				<tr>
					<td style="width: 49%; border: 1px solid black; vertical-align: top; margin-right: 1%;">
						<table style="width: 100%; border-collapse: collapse; font-size: 14px;">
							<tr>
								<td colspan="1" style="background-color: #e5e7eb; text-align: center; font-weight: 600; font-size: 0.875rem; border-bottom: 1px solid black; padding: 0.5rem;">
									Prepared For
								</td>
							</tr>
							<tr>
								<td style="padding: 0.5rem; font-weight: 700; text-transform: uppercase;">
									{{ $name }}
								</td>
							</tr>
							<tr>
								<td style="padding: 0.5rem 0.5rem 0 0.5rem;">
									{{ $address }}, {{ $city }}, {{ $country }}
								</td>
							</tr>
							<tr>
								<td style="padding: 0.5rem;">
									<strong>Email:</strong> {{ $email }}
								</td>
							</tr>
						</table>
					</td>

					<td style="width: 48%; vertical-align: top;">
						<table style=" margin-left: 2%;  width: 98%; border-collapse: collapse; font-size: 12px; border: 1px solid black;">
							<tr style="background-color: #e5e7eb; text-align: center;">
								<td style="border: 1px solid black; padding: 0.5rem; font-weight: 700;">Quotation Date</td>
								<td style="border: 1px solid black; padding: 0.5rem; font-weight: 700;">Expiry Date</td>
								<td style="border: 1px solid black; padding: 0.5rem; font-weight: 700;">Quotation No</td>
							</tr>
							<tr style="text-align: center;">
								<td style="border: 1px solid black; padding: 0.5rem;">{{ $createdAt }}</td>
								<td style="border: 1px solid black; padding: 0.5rem; color: #dc2626; font-weight: 600;">{{ $expiredAt }}</td>
								<td style="border: 1px solid black; padding: 0.5rem;">{{ $quoteNumber }}</td>
							</tr>
							<tr style="background-color: #e5e7eb; text-align: center;">
								<td style="border: 1px solid black; padding: 0.5rem; font-weight: 700;">Payment Mode</td>
								<td style="border: 1px solid black; padding: 0.5rem; font-weight: 700;">Quotation Type</td>
								<td style="border: 1px solid black; padding: 0.5rem; font-weight: 700;">Currency</td>
							</tr>
							<tr style="text-align: center;">
								<td style="border: 1px solid black; padding: 0.5rem;">{{ $paymentMode }}</td>
								<td style="border: 1px solid black; padding: 0.5rem;">{{ $quoteType }}</td>
								<td style="border: 1px solid black; padding: 0.5rem; color: #dc2626; font-weight: 600;">{{ $currency }}</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>

			<table style="padding: 0.5rem; width: 100%;  margin-bottom: 1rem;  border: 1px solid #d1d5db; font-size: 11px; border-collapse: collapse;">
				<thead>
					<tr style="background-color: #e5e7eb; text-align: center; font-weight: 600;">
						<th style="border: 1px solid black; padding: 0.5rem;">Quotation Date</th>
						<th style="border: 1px solid black; padding: 0.5rem;">Expiry Date</th>
						<th style="border: 1px solid black; padding: 0.5rem;">Quotation No</th>
						<th style="border: 1px solid black; padding: 0.5rem;">Paymen Mode</th>
						<th style="border: 1px solid black; padding: 0.5rem;">Quotation Type</th>
						<th style="border: 1px solid black; padding: 0.5rem;">Currency</th>
					</tr>
				</thead>
				<tbody>
					<tr style="text-align: center;">
						<td style="border: 1px solid black; padding: 0.5rem;">{{ $createdAt }}</td>
						<td style="border: 1px solid black; padding: 0.5rem; color: #FF0000;">{{ $expiredAt }}</td>
						<td style="border: 1px solid black; padding: 0.5rem;">{{ $quoteNumber }}</td>
						<td style="border: 1px solid black; padding: 0.5rem;">{{ $paymentMode }}</td>
						<td style="border: 1px solid black; padding: 0.5rem;">{{ $quoteType }}</td>
						<td style="border: 1px solid black; padding: 0.5rem; color: #FF0000;">{{ $currency }}</td>
					</tr>
				</tbody>
			</table>


			<table style="width:100%; border-collapse:collapse; border:1px solid black; font-size:11px;">
				<thead>
					<tr style="background-color:#e5e7eb;">
						<th style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; font-weight:bold; width:5%;">S.No</th>
						<th style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; font-weight:bold; width:35%;">Description</th>
						<th style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; font-weight:bold; width:10%;">Image</th>
						<th style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; font-weight:bold; width:8%;">Quantity</th>
						<th style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; font-weight:bold; width:8%;">UNIT</th>
						<th style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; font-weight:bold; width:10%;">Unit Price</th>
						<th style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; font-weight:bold; width:12%;">Total</th>
					</tr>
				</thead>
				<tbody>
					@foreach($products as $product)
					<tr style="background-color:white;">
						<td style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; text-align:center;">1</td>
						<td style="border-top:1px solid black; border-bottom:1px solid black; padding:8px;">
							<div style="margin-bottom:4px;">
								<p style="font-weight:bold; font-size:12px; margin-bottom:2px;">
									Reach-In Refrigerator, 48", 2 Doors, 36 Cu. Ft., Stainless Steel, 6-Year
									Warrant...
								</p>
								<p style="font-size:11px; margin-bottom:2px;">
									<span style="font-weight:bold;">Brand:</span>
									<span style="color:#dc2626;">{{ $product->brandName }}</span> |
									<span style="font-weight:bold;">SKU #:</span>
									<span style="color:#dc2626;">{{ $product->sku }}</span>
								</p>
								<p style="font-size:11px; margin-bottom:3px;">
									<span style="font-weight:bold;">Warranty :</span>
									<span style="color:#dc2626;">{{ $product->warrantyInfo }}</span>
								</p>
								<p style="font-size:11px; margin-bottom:3px;">
									<span style="font-weight:bold; color:#1B6738;">{{ $product->shippingCharge }}</span>
									<span style="font-weight:bold;">Mostly Ships in {{ $product->deliveryDays }}</span>
								</p>
								<a href="{{ $product->productURL }}" target="_blank" rel="noopener noreferrer" style="color:#2563eb; font-size:9.7px;">
									Click here for more details
								</a>
							</div>
						</td>
						<td style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; text-align:center; vertical-align:middle;">
							@if (!empty($product->localImagePath) && file_exists($product->localImagePath))
								<img src="{{ $product->localImagePath }}" alt="Product Image" style="max-width: 60px; max-height: 60px; width: auto; height: auto;">
							@else
								<div style="width: 60px; height: 60px; background-color: #f3f4f6; border: 1px solid #d1d5db; text-align: center; line-height: 60px; font-size: 10px; color: #6b7280;">
									No Image
								</div>
							@endif
						</td>

						<td style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; text-align:center;">{{ $product->quantity }}</td>
						<td style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; text-align:center;">{{ $product->sellingType }}</td>
						<td style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; text-align:center;">{{ $product->unitPrice }}</td>
						<td style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; text-align:center;">{{ $product->total}}</td>
					</tr>
					@endforeach
				</tbody>
			</table>

			<table style="width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 15px;">
				<tr>
					<td style="width: 60%; vertical-align: top; border: 1px solid black; padding: 16px; background-color: #ffffff;">
						<p style="font-size: 12px; font-weight: 600;">TERMS OF SALE</p>
						<ul style="margin-top: 8px; font-size: 12px; list-style: none; padding: 0;">
							<li style="display: flex; align-items: flex-start; margin-bottom: 8px;">
								<span style="font-size: 13px; margin-right: 8px; margin-top: -1px;">•</span>
								<span>Kindly include our Order No & Date while processing the payment through bank transfer.</span>
							</li>
							<li style="display: flex; align-items: flex-start; margin-bottom: 8px;">
								<span style="font-size: 13px; margin-right: 8px; margin-top: -1px;">•</span>
								<span>Stock levels change daily; availability confirmed only at the point of purchase with valid LPO or Advance Payment.</span>
							</li>
							<li style="display: flex; align-items: flex-start; margin-bottom: 8px;">
								<span style="font-size: 13px; margin-right: 8px; margin-top: -1px;">•</span>
								<span>Lead times are from the receipt of payment unless agreed otherwise.</span>
							</li>
							<li style="display: flex; align-items: flex-start; margin-bottom: 8px;">
								<span style="font-size: 13px; margin-right: 8px; margin-top: -1px;">•</span>
								<span>Lead times are based on manufacturing times and may be subject to change.</span>
							</li>
							<li style="display: flex; align-items: flex-start; margin-bottom: 8px;">
								<span style="font-size: 13px; margin-right: 8px; margin-top: -1px;">•</span>
								<span>Once items are available, delivery must be accepted/received within 2 weeks.</span>
							</li>
							<li style="display: flex; align-items: flex-start; margin-bottom: 8px;">
								<span style="font-size: 13px; margin-right: 8px; margin-top: -1px;">•</span>
								<span>If delivery is delayed by the customer, storage charges may apply. Installation not included unless agreed.</span>
							</li>
						</ul>
					</td>

					<!-- Invoice Summary Column -->
					<td style="width: 40%; vertical-align: top; border: 1px solid black; background-color: #ffffff; padding: 0;">
						<div style="padding: 16px;">
							<table style="width: 100%; font-size: 12px;">
								<tbody>
									<tr>
										<td style="text-align: left; padding-top: 4px; padding-bottom: 4px; font-weight: 600;">INVOICE SUBTOTAL</td>
										<td style="text-align: right; padding-top: 4px; padding-bottom: 4px;">{{ $subTotal }}</td>
									</tr>
									<tr>
										<td style="text-align: left; padding-top: 4px; padding-bottom: 4px; font-weight: 600;">TOTAL W/O TAX</td>
										<td style="text-align: right; padding-top: 4px; padding-bottom: 4px;">{{ $shippingCharge }}</td>
									</tr>
									<tr>
										<td style="text-align: left; padding-top: 4px; padding-bottom: 4px; font-weight: 600;">{{ $taxName }} ({{ $taxPercent }}%)</td>
										<td style="text-align: right; padding-top: 4px; padding-bottom: 4px;">{{ $taxAmount }}</td>
									</tr>
								</tbody>
							</table>
						</div>
						<p style="display: flex; justify-content: space-between; color: #FF0000; background-color: #E7E7E7; padding: 8px 8px; font-weight: 600;">
							<span>NET TOTAL INCL.{{ $taxName }}</span>
							<span>{{ $total }}</span>
						</p>
						<div style="text-align: right; padding: 8px 8px 8px 16px;">
							<p style="font-weight: 600;">
								{{ $totalInWords }}
							</p>
						</div>
					</td>
				</tr>
			</table>

			<table style="width: 100%; border-spacing: 0; margin-top: 0px;">
				<tr>
					<!-- Bank Details Table Cell -->
					<td style="width: 50%; vertical-align: top; padding: 15px 0px;">
						<table style="width: 100%; border-collapse: collapse; font-size: 12px; border: 1px solid black;">
							<tr>
								<td colspan="2" style="background-color: #e5e7eb; text-align: center; padding: 8px; font-weight: 600; font-size: 1rem;">
									Bank Details
								</td>
							</tr>
							<tr>
								<td style="background-color: #E7E7E7; border-top: 1px solid black; padding: 8px; font-weight: 600; white-space: nowrap;">
									Account Name
								</td>
								<td style="border-top: 1px solid black; padding: 4px 8px 16px 4px; font-weight: 600;">
									{{ $companyName }}
								</td>
							</tr>
							<tr>
								<td style="background-color: #E7E7E7; border-top: 1px solid black; padding: 8px; font-weight: 600; white-space: nowrap;">
									Beneficiary Address
								</td>
								<td style="border-top: 1px solid black; padding: 4px 8px 16px 4px; font-weight: 600;">
									{{ $beneficiaryAddress }}
								</td>
							</tr>
							<tr>
								<td style="background-color: #E7E7E7; border-top: 1px solid black; padding: 8px; font-weight: 600; white-space: nowrap;">
									Account No
								</td>
								<td style="border-top: 1px solid black; padding: 4px 8px 16px 4px; font-weight: 600;">
									{{ $accountNo }}
								</td>
							</tr>
							<tr>
								<td style="background-color: #E7E7E7; border-top: 1px solid black; padding: 8px; font-weight: 600; white-space: nowrap;">
									Bank
								</td>
								<td style="border-top: 1px solid black; padding: 4px 8px 16px 4px; font-weight: 600;">
									{{ $bankName }}
								</td>
							</tr>
							<tr>
								<td style="background-color: #E7E7E7; border-top: 1px solid black; padding: 8px; font-weight: 600; white-space: nowrap;">
									Routing Code
								</td>
								<td style="border-top: 1px solid black; padding: 4px 8px 16px 4px; font-weight: 600;">
									{{ $routingCode }}
								</td>
							</tr>
							<tr>
								<td style="background-color: #E7E7E7; border-top: 1px solid black; padding: 8px; font-weight: 600; white-space: nowrap;">
									In Case Of Cheque Payment
								</td>
								<td style="border-top: 1px solid black; padding: 4px 8px 20px 4px; font-weight: 600;">
									Please prepare all cheques in favor of <br>
									<span style="color: #FF0000;">{{ $companyName }}</span>
								</td>
							</tr>
						</table>
					</td>

					<!-- Payment Terms Table Cell -->
					<td style="width: 50%; vertical-align: top; padding: 15px 0px 15px 15px;">
						<table style="width: 100%; border: 1px solid black; background-color: white; font-size: 12px; border-collapse: collapse;">
							<thead>
								<tr style="background-color: #E5E7EB;">
									<th colspan="2" style="text-align: center; padding: 8px; font-weight: 600; font-size: 16px;">
										Customer Payment Terms & Order Processing Timeline
									</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="2" style="padding: 8px; font-size: 12px; line-height: 1.6; border-top: 1px solid black;">
										<strong>Bank Transfer (Wire/Local):</strong> Orders are processed after 2–3 business days upon receipt of funds.<br>
										<strong>ACH Payments:</strong> Orders are processed after 3 business days from payment receipt.<br>
										<strong>Personal Checks:</strong> Orders are processed after 5 business days from deposit date.<br>
										<strong>Corporate Checks:</strong> Orders are processed after 3 business days from deposit date.<br>
										<strong>Cash & Card Payments:</strong> Orders are processed immediately upon successful payment.<br>
										<strong>Online Payments (Credit/Debit):</strong> Subject to fraud screening—may take 1–2 extra business days if flagged.<br>
										<strong>Confirmation:</strong> Orders are confirmed only after payment clearance and fraud checks are completed.
									</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
			</table>

			<p style="font-weight: 600; text-align: center; margin-bottom: 20px;">
				This is a system generated Invoice. Hence, no stamp or signature required.
			</p>

			<table style="width: 100%; border-top: 1px solid black; margin-top: 10px; padding-top: 8px; font-size: 12px;">
				<tr>
					<td style="padding: 8px; text-align: left;">
						Order Online for Fast Shipping & Lower Prices at
						<a href="{{ $siteURL }}" target="_blank" rel="noopener noreferrer"
						style="color: #15803d;">{{ $siteURL }}</a>
					</td>
					<td style="padding: 8px; text-align: right;">
						Page 1 of 1
					</td>
				</tr>
			</table>
		</div>
	</div>
</body>
</html>