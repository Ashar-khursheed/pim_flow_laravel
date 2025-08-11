<!DOCTYPE html>

<html lang="en">

<head>
	<meta charset="utf-8" />
	<meta content="width=device-width, initial-scale=1.0" name="viewport" />
	<title>Sales Quotation Design</title>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
	<script src="https://cdn.tailwindcss.com"></script>
</head>
@php
	use Illuminate\Support\Str;

	$chunks = [];
	$offset = 0;
	$total = $products->count();
	$pattern = [5];

	while (array_sum($pattern) < $total) {
		$pattern[] = 4;
	}

	foreach ($pattern as $size) {
		if ($offset >= $total) break;
		$chunks[] = $products->slice($offset, $size);
		$offset += $size;
	}
@endphp
<body>
	<div id="targetDiv" style="width: auto; min-height: 290mm; margin: 0px auto;  font-size: 12px; line-height: 1.3; font-family: Outfit;background-color: white;">
		<div style="min-height: 1070px; height: 1070px; display: flex; flex-direction: column; padding: 20px; box-sizing: border-box;background-color: white;">
			@foreach($chunks as $index => $chunk)
				@if($index > 0)
					<div style="page-break-before: always;"></div>
				@endif

				<table style="width: 100%; border-collapse: collapse; margin-bottom: 1rem; font-size: 14px;">
					<tr>
						<td style="width: 33.33%; padding: 0.2rem 0.5rem ; vertical-align: top;">
							<img alt="logo" src="{{ $pdfLogoUrl }}" width="120"/>
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

				@if($index === 0)
					<table style="width: 100%; border-collapse: collapse; margin-bottom: 1rem; font-size: 14px; font-family: 'Inter', sans-serif;">
						<tr>
							<td style="width: 49%; border: 1px solid black; vertical-align: top; padding: 0;">
								<table style="width: 100%; border-collapse: collapse; font-size: 14px; font-family: 'Inter', sans-serif;">
									<tr>
										<td colspan="1" style="background-color: #d9d9d9; text-align: center; font-weight: 600; font-size: 14px; padding: 10px 8px 8px;">
											Prepared For
										</td>
									</tr>
									<tr>
										<td style="padding: 6px 0.5rem;; font-weight: 700; text-transform: uppercase;">
											{{ $name }}
										</td>
									</tr>
									<tr>
										<td style="padding: 0 0.5rem;">
											{{ $address }}, {{ $city }}, {{ $country }}
										</td>
									</tr>
									<tr>
										<td style="padding: 0 0.5rem;">
											{{ $address }}, {{ $city }}, {{ $country }}
										</td>
									</tr>
									<tr>
										<td style="padding: 1rem 0.5rem 0.1rem;">
											<strong>Telephone::</strong> {{ $email }}
										</td>
									</tr>
									<tr>
										<td style="padding: 0.1rem 0.5rem;">
											<strong>Email:</strong> {{ $email }}
										</td>
									</tr>
								</table>
							</td>

							<td style="width: 48%; vertical-align: top;">
								<table style=" margin-left: 2%;  width: 98%; border-collapse: collapse; font-size: 12px; border: 1px solid black; font-family: 'Inter', sans-serif;">
									<tr style="background-color: #d9d9d9; text-align: center;">
										<td style="border: 1px solid black; padding: 0.5rem; font-weight: 700;">Quotation Date</td>
										<td style="border: 1px solid black; padding: 0.5rem; font-weight: 700;">Expiry Date</td>
										<td style="border: 1px solid black; padding: 0.5rem; font-weight: 700;">Quotation No</td>
									</tr>
									<tr style="text-align: center;">
										<td style="border: 1px solid black; padding: 0.5rem;">{{ $createdAt }}</td>
										<td style="border: 1px solid black; padding: 0.5rem; color: #dc2626; font-weight: 600;">{{ $expiredAt }}</td>
										<td style="border: 1px solid black; padding: 0.5rem;">{{ $quoteNumber }}</td>
									</tr>
									<tr style="background-color: #d9d9d9; text-align: center;">
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
				@else
					<table style="padding: 0.5rem; width: 100%;  margin-bottom: 1rem;  border: 1px solid #d1d5db; font-size: 11px; border-collapse: collapse; font-family: 'Inter', sans-serif;">
						<thead>
							<tr style="background-color: #d9d9d9; text-align: center; font-weight: 600; font-family: 'Inter', sans-serif;">
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
								<td style="border: 1px solid black; padding: 0.5rem; font-family: 'Inter', sans-serif;">{{ $createdAt }}</td>
								<td style="border: 1px solid black; padding: 0.5rem; color: #FF0000; font-family: 'Inter', sans-serif;">{{ $expiredAt }}</td>
								<td style="border: 1px solid black; padding: 0.5rem; font-family: 'Inter', sans-serif;">{{ $quoteNumber }}</td>
								<td style="border: 1px solid black; padding: 0.5rem; font-family: 'Inter', sans-serif;">{{ $paymentMode }}</td>
								<td style="border: 1px solid black; padding: 0.5rem; font-family: 'Inter', sans-serif;">{{ $quoteType }}</td>
								<td style="border: 1px solid black; padding: 0.5rem; color: #FF0000; font-family: 'Inter', sans-serif;">{{ $currency }}</td>
							</tr>
						</tbody>
					</table>
				@endif


				<table style="width:100%; border-collapse:collapse; border:1px solid black; font-size:11px; font-family: 'Inter', sans-serif;">
					<thead>
						<tr style="background-color:#d9d9d9;">
							<th style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; font-weight:bold; width:5%; font-family: 'Inter', sans-serif;">S.No</th>
							<th style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; font-weight:bold; width:35%; font-family: 'Inter', sans-serif;">Description</th>
							<th style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; font-weight:bold; width:10%; font-family: 'Inter', sans-serif;">Image</th>
							<th style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; font-weight:bold; width:8%; font-family: 'Inter', sans-serif;">Quantity</th>
							<th style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; font-weight:bold; width:8%; font-family: 'Inter', sans-serif;">UNIT</th>
							<th style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; font-weight:bold; width:10%; font-family: 'Inter', sans-serif;">Unit Price</th>
							<th style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; font-weight:bold; width:12% ; font-family: 'Inter', sans-serif;">Total</th>
						</tr>
					</thead>
					<tbody>
						@foreach($chunk  as $index1 => $product)
						<tr style="background-color:white; font-family: 'Inter', sans-serif;">
							<td style="border-top:1px solid black; border-bottom:1px solid black;  text-align:center; font-family: 'Inter', sans-serif;">{{ $index1+1 }}</td>
							<td style="border-top:1px solid black; border-bottom:1px solid black;  font-family: 'Inter', sans-serif;">
								<div style="margin-bottom:4px; font-family: 'Inter', sans-serif;">
									<p style="font-weight:bold; font-size:13px; margin-bottom:2px; font-family: 'Inter', sans-serif;">
										{{ Str::limit($product->name, 90, '...') }}
									</p>
									<p style="font-size:11px; margin-bottom:2px; font-family: 'Inter', sans-serif;">
										<span style="font-weight:bold; font-family: 'Inter', sans-serif;">Brand:</span>
										<span style="color:#dc2626; font-family: 'Inter', sans-serif;">{{ $product->brandName }}</span> |
										<span style="font-weight:bold; font-family: 'Inter', sans-serif;">SKU #:</span>
										<span style="color:#dc2626; font-family: 'Inter', sans-serif;">{{ $product->sku }}</span>
									</p>
									<p style="font-size:11px; margin-bottom:3px; font-family: 'Inter', sans-serif;">
										<span style="font-weight:bold; font-family: 'Inter', sans-serif;">Warranty :</span>
										<span style="color:#dc2626; font-family: 'Inter', sans-serif;">{{ $product->warrantyInfo }}</span>
									</p>
									<p style="font-size:11px; margin-bottom:3px; font-family: 'Inter', sans-serif;">
										<span style="font-weight:bold; color:#186737; font-family: 'Inter', sans-serif;">{{ $product->shippingCharge }}</span>
										<span style="font-weight:bold; font-family: 'Inter', sans-serif;">Mostly Ships in {{ $product->deliveryDays }}</span>
									</p>
									<a href="{{ $product->productURL }}" target="_blank" rel="noopener noreferrer" style="color:#2563eb; font-size:9.7px;">
										Click here for more details
									</a>
								</div>
							</td>
							<td style="border-top:1px solid black; border-bottom:1px solid black;  text-align:center; vertical-align:middle; font-family: 'Inter', sans-serif;">
								@if (!empty($product->base64_image))
									<img src="{{ $product->base64_image }}" alt="Product Image" style="max-width: 60px; max-height: 60px; width: auto; height: auto; font-family: 'Inter', sans-serif;">
								@else
									<div style="width: 60px; height: 60px; background-color: #f3f4f6; border: 1px solid #d1d5db; text-align: center; line-height: 60px; font-size: 10px; color: #6b7280; font-family: 'Inter', sans-serif;">
										No Image
									</div>
								@endif
							</td>

							<td style="border-top:1px solid black; border-bottom:1px solid black;  text-align:center; font-family: 'Inter', sans-serif;">{{ $product->quantity }}</td>
							<td style="border-top:1px solid black; border-bottom:1px solid black;  text-align:center; font-family: 'Inter', sans-serif;">{{ $product->sellingType }}</td>
							<td style="border-top:1px solid black; border-bottom:1px solid black;  text-align:center; font-family: 'Inter', sans-serif;">{{ $product->unitPrice }}</td>
							<td style="border-top:1px solid black; border-bottom:1px solid black;  text-align:center; font-family: 'Inter', sans-serif;">{{ $product->total}}</td>
						</tr>
						@endforeach
					</tbody>
				</table>

				<table style="width: 100%; border-top: 1px solid black; margin-top: 10px; padding-top: 2px; font-size: 12px; font-family: 'Inter', sans-serif;">
					<tr>
						<td style="text-align: left;">
							Order Online for Fast Shipping & Lower Prices at
							<a href="{{ $siteURL }}" target="_blank" rel="noopener noreferrer" style="color: #186737; font-family: 'Inter', sans-serif;">{{ $siteURL }}</a>
						</td>
						<td style="text-align: right; font-family: 'Inter', sans-serif;">
							{{-- Page {{ $index+1 }} of {{ count($chunks) }} --}}
							Page {{ $index+1 }}
						</td>
					</tr>
				</table>
			@endforeach

			<table style="width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 15px; font-family: 'Inter', sans-serif;">
				<tr>
					<td style="width: 60%; vertical-align: top; border: 1px solid black; padding: 16px; background-color: #ffffff; font-family: 'Inter', sans-serif;">
						<p style="font-size: 12px; font-weight: 600; font-family: 'Inter', sans-serif;">TERMS OF SALE</p>
						<ul style="margin-top: 8px; font-size: 12px; list-style: none; padding: 0; font-family: 'Inter', sans-serif;">
							<li style="display: flex; align-items: flex-start; margin-bottom: 8px; font-family: 'Inter', sans-serif;">
								<span style="font-size: 13px; margin-right: 8px; margin-top: -1px; font-family: 'Inter', sans-serif;">•</span>
								<span>Kindly include our Order No & Date while processing the payment through bank transfer.</span>
							</li>
							<li style="display: flex; align-items: flex-start; margin-bottom: 8px; font-family: 'Inter', sans-serif;">
								<span style="font-size: 13px; margin-right: 8px; margin-top: -1px; font-family: 'Inter', sans-serif;">•</span>
								<span>Stock levels change daily; availability confirmed only at the point of purchase with valid LPO or Advance Payment.</span>
							</li>
							<li style="display: flex; align-items: flex-start; margin-bottom: 8px; font-family: 'Inter', sans-serif;">
								<span style="font-size: 13px; margin-right: 8px; margin-top: -1px; font-family: 'Inter', sans-serif;">•</span>
								<span>Lead times are from the receipt of payment unless agreed otherwise.</span>
							</li>
							<li style="display: flex; align-items: flex-start; margin-bottom: 8px; font-family: 'Inter', sans-serif;">
								<span style="font-size: 13px; margin-right: 8px; margin-top: -1px; font-family: 'Inter', sans-serif;">•</span>
								<span>Lead times are based on manufacturing times and may be subject to change.</span>
							</li>
							<li style="display: flex; align-items: flex-start; margin-bottom: 8px; font-family: 'Inter', sans-serif;">
								<span style="font-size: 13px; margin-right: 8px; margin-top: -1px; font-family: 'Inter', sans-serif;">•</span>
								<span>Once items are available, delivery must be accepted/received within 2 weeks.</span>
							</li>
							<li style="display: flex; align-items: flex-start; margin-bottom: 8px; font-family: 'Inter', sans-serif;">
								<span style="font-size: 13px; margin-right: 8px; margin-top: -1px; font-family: 'Inter', sans-serif;">•</span>
								<span>If delivery is delayed by the customer, storage charges may apply. Installation not included unless agreed.</span>
							</li>
						</ul>
					</td>

					<!-- Invoice Summary Column -->
					<td style="width: 40%; vertical-align: top; border: 1px solid black; background-color: #ffffff; padding: 0; font-family: 'Inter', sans-serif;">
						<div style="padding: 16px; font-family: 'Inter', sans-serif;">
							<table style="width: 100%; font-size: 12px; font-family: 'Inter', sans-serif;">
								<tbody>
									<tr>
										<td style="text-align: left; padding-top: 4px; padding-bottom: 4px; font-weight: 600; font-family: 'Inter', sans-serif;">INVOICE SUBTOTAL</td>
										<td style="text-align: right; padding-top: 4px; padding-bottom: 4px; font-family: 'Inter', sans-serif;">{{ $subTotal }}</td>
									</tr>
									<tr>
										<td style="text-align: left; padding-top: 4px; padding-bottom: 4px; font-weight: 600; font-family: 'Inter', sans-serif;">TOTAL W/O TAX</td>
										<td style="text-align: right; padding-top: 4px; padding-bottom: 4px; font-family: 'Inter', sans-serif; font-family: 'Inter', sans-serif;">{{ $shippingCharge }}</td>
									</tr>
									<tr>
										<td style="text-align: left; padding-top: 4px; padding-bottom: 4px; font-weight: 600; font-family: 'Inter', sans-serif;">{{ $taxName }} ({{ $taxPercent }}%)</td>
										<td style="text-align: right; padding-top: 4px; padding-bottom: 4px; font-family: 'Inter', sans-serif;">{{ $taxAmount }}</td>
									</tr>
								</tbody>
							</table>
						</div>
						<p style="display: flex; justify-content: space-between; color: #FF0000; background-color: #E7E7E7; padding: 8px 8px; font-weight: 600; font-family: 'Inter', sans-serif;">
							<span>NET TOTAL INCL.{{ $taxName }}</span>
							<span>{{ $total }}</span>
						</p>
						<div style="text-align: right; padding: 8px 8px 8px 16px; font-family: 'Inter', sans-serif;">
							<p style="font-weight: 600;">
								{{ $totalInWords }}
							</p>
						</div>
					</td>
				</tr>
			</table>

			<table style="width: 100%; border-spacing: 0; margin-top: 0px; font-family: 'Inter', sans-serif;">
				<tr>
					<!-- Bank Details Table Cell -->
					<td style="width: 50%; vertical-align: top; padding: 15px 0px; font-family: 'Inter', sans-serif;">
						<table style="width: 100%; border-collapse: collapse; font-size: 12px; border: 1px solid black; font-family: 'Inter', sans-serif;">
							<tr>
								<td colspan="2" style="background-color: #d9d9d9; text-align: center; padding: 8px; font-weight: 600; font-size: 1rem; font-family: 'Inter', sans-serif;">
									Bank Details
								</td>
							</tr>
							<tr>
								<td style="background-color: #E7E7E7; border-top: 1px solid black; padding: 8px; font-weight: 600; white-space: nowrap; font-family: 'Inter', sans-serif;">
									Account Name
								</td>
								<td style="border-top: 1px solid black; padding: 4px 8px 16px 4px; font-weight: 600; font-family: 'Inter', sans-serif;">
									{{ $companyName }}
								</td>
							</tr>
							<tr>
								<td style="background-color: #E7E7E7; border-top: 1px solid black; padding: 8px; font-weight: 600; white-space: nowrap; font-family: 'Inter', sans-serif;">
									Beneficiary Address
								</td>
								<td style="border-top: 1px solid black; padding: 4px 8px 16px 4px; font-weight: 600; font-family: 'Inter', sans-serif;">
									{{ $beneficiaryAddress }}
								</td>
							</tr>
							<tr>
								<td style="background-color: #E7E7E7; border-top: 1px solid black; padding: 8px; font-weight: 600; white-space: nowrap; font-family: 'Inter', sans-serif;">
									Account No
								</td>
								<td style="border-top: 1px solid black; padding: 4px 8px 16px 4px; font-weight: 600; font-family: 'Inter', sans-serif;">
									{{ $accountNo }}
								</td>
							</tr>
							<tr>
								<td style="background-color: #E7E7E7; border-top: 1px solid black; padding: 8px; font-weight: 600; white-space: nowrap; font-family: 'Inter', sans-serif;">
									Bank
								</td>
								<td style="border-top: 1px solid black; padding: 4px 8px 16px 4px; font-weight: 600; font-family: 'Inter', sans-serif;">
									{{ $bankName }}
								</td>
							</tr>
							<tr>
								<td style="background-color: #E7E7E7; border-top: 1px solid black; padding: 8px; font-weight: 600; white-space: nowrap; font-family: 'Inter', sans-serif;">
									Routing Code
								</td>
								<td style="border-top: 1px solid black; padding: 4px 8px 16px 4px; font-weight: 600; font-family: 'Inter', sans-serif;">
									{{ $routingCode }}
								</td>
							</tr>
							<tr>
								<td style="background-color: #E7E7E7; border-top: 1px solid black; padding: 8px; font-weight: 600; white-space: nowrap; font-family: 'Inter', sans-serif;">
									In Case Of Cheque Payment
								</td>
								<td style="border-top: 1px solid black; padding: 4px 8px 20px 4px; font-weight: 600; font-family: 'Inter', sans-serif;">
									Please prepare all cheques in favor of <br>
									<span style="color: #FF0000;">{{ $companyName }}</span>
								</td>
							</tr>
						</table>
					</td>

					<!-- Payment Terms Table Cell -->
					<td style="width: 50%; vertical-align: top; padding: 15px 0px 15px 15px; font-family: 'Inter', sans-serif;">
						<table style="width: 100%; border: 1px solid black; background-color: white; font-size: 12px; border-collapse: collapse; font-family: 'Inter', sans-serif;">
							<thead>
								<tr style="background-color: #d9d9d9; font-family: 'Inter', sans-serif;">
									<th colspan="2" style="text-align: center; padding: 8px; font-weight: 600; font-size: 16px; font-family: 'Inter', sans-serif;">
										Customer Payment Terms & Order Processing Timeline
									</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="2" style="padding: 8px; font-size: 12px; line-height: 1.6; border-top: 1px solid black; font-family: 'Inter', sans-serif;">
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

			<p style="font-weight: 600; text-align: center; margin-bottom: 20px; font-family: 'Inter', sans-serif;">
				This is a system generated Invoice. Hence, no stamp or signature required.
			</p>

			<table style="width: 100%; border-top: 1px solid black; margin-top: 10px; padding-top: 8px; font-size: 12px; font-family: 'Inter', sans-serif;">
				<tr>
					<td style="padding: 8px; text-align: left; font-family: 'Inter', sans-serif;">
						Order Online for Fast Shipping & Lower Prices at
						<a href="{{ $siteURL }}" target="_blank" rel="noopener noreferrer" style="color: #186737; font-family: 'Inter', sans-serif;">{{ $siteURL }}</a>
					</td>
					<td style="padding: 8px; text-align: right; font-family: 'Inter', sans-serif;">
						{{-- Page 1 of 1 --}}
					</td>
				</tr>
			</table>
		</div>
	</div>
</body>
</html>