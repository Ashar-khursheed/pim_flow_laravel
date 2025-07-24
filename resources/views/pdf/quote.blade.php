<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Sales Quotation Design</title>
	<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
	<script src="https://cdn.tailwindcss.com"></script>
	<style>
		/* Base styles, primarily for the root layout and print media */
		body {
			font-family: 'Outfit', sans-serif;
			/* Apply Outfit font globally */
			@apply m-0 p-0 bg-gray-100 flex justify-center items-start min-h-screen;
		}

		/* Target Div for the A4 page layout */
		#targetDiv {
			@apply w-[210mm] min-h-[290mm] mx-auto p-[5mm] text-sm leading-tight font-outfit bg-white shadow-lg box-border;
		}

		/* Quotation Page structure */
		.quotation-page {
			@apply h-[1070px] flex flex-col justify-between p-[5px] box-border;
			/* Fixed height for first page design */
		}

		/* Print-specific styles using Tailwind's print utility */
		@media print {
			body {
				@apply bg-white;
			}

			#targetDiv {
				@apply shadow-none m-0 p-0 text-xs;
				/* Slightly smaller font for print */
			}

			.quotation-page {
				@apply h-auto min-h-[297mm] print:break-after-page;
				/* Allow content to flow for print, ensure full A4 height, page break */
			}

			.quotation-page:last-of-type {
				@apply print:break-after-avoid;
			}

			/* Adjust padding/gap for print to be tighter */
			.header-section,
			.details-section,
			.products-table-container,
			.summary-and-terms,
			.bank-payment-section,
			.footer-links {
				@apply print:px-[5mm] print:py-[2mm] print:gap-[5mm];
			}

			.products-table th,
			.products-table td {
				@apply print:p-[2mm_3mm];
			}

			.customer-details .header,
			.invoice-details th,
			.bank-details .header,
			.payment-terms .header {
				@apply print:p-[1mm_2mm];
			}

			.customer-details .content,
			.summary-box .summary-content,
			.payment-terms .content {
				@apply print:p-[3mm_5mm];
			}

			.bank-details td {
				@apply print:p-[1mm_2mm];
			}

			.summary-box .net-total {
				@apply print:p-[2mm_5mm];
			}

			.summary-box .amount-in-words {
				@apply print:p-[4mm_5mm];
			}

			.footer-text {
				@apply print:mt-[10mm] print:mb-[10mm];
			}
		}

		/* Custom styles for specific elements not easily mapped or for more semantic naming */
		/* You might still use some custom classes for very specific layouts or complex elements */
		.products-table th,
		.products-table td {
			border-top: 1px solid black;
			border-bottom: 1px solid black;
			vertical-align: top;
		}

		.products-table th:nth-child(2) {
			text-align: left;
		}

		.products-table td:nth-child(2) {
			text-align: left;
		}

		.invoice-details-table th,
		.invoice-details-table td {
			border: 1px solid black;
		}

		.bank-details table td {
			border-top: 1px solid black;
		}

		.bank-details td:first-child {
			@apply bg-gray-200 font-semibold whitespace-nowrap px-1 pr-2;
			/* Match custom bg-gray-200 for first td */
		}

		.bank-details td:last-child {
			@apply font-semibold px-1 pr-2;
		}
	</style>
</head>
@php
use Illuminate\Support\Str;
@endphp
<body>
	<div id="targetDiv" bis_skin_checked="1" style="width: auto; min-height: 290mm; margin: 0px auto; padding: 5mm; font-size: 12px; line-height: 1.3; font-family: Outfit; background-color: white;">
		<div class="bg-white" bis_skin_checked="1" style="min-height: 1070px; height: 1070px; display: flex; flex-direction: column; justify-content: space-between; padding: 50px; box-sizing: border-box;">
			<div class="flex  flex-col justify-between h-full" bis_skin_checked="1">
				<div class="grid grid-cols-3 gap-4 p-2" bis_skin_checked="1">
					<div class="flex items-center" bis_skin_checked="1">
						<img class="h-30 w-40" src="{{ $logoUrl }}" alt="logo">
					</div>
					<div class="text-center" bis_skin_checked="1">
						<h1 class="text-[16px] font-bold">SALES QUOTATION</h1>
						<p class="text-[13px] font-bold text-[#186737]">Best Price. Zero Hassle.</p>
					</div>
					<div class="text-right text-[11px]" bis_skin_checked="1">
						<p class="font-bold">{{ $companyName }}.</p>
						<p>{{ $street }}</p>
						<p>{{ $city }}</p>
						<p>Phone: {{ $phone }}</p>
						<p>Email: {{ $siteEmail }}</p>
						<p>{{ $siteURL }}</p>
					</div>
				</div>

				<div class="grid grid-cols-2 gap-4 p-2" bis_skin_checked="1">
					<div class="border border-black" bis_skin_checked="1">
						<div class="bg-gray-200  pl-2 pr-2 pb-2 pt-2 text-center font-semibold text-sm" bis_skin_checked="1">
							Prepared For
						</div>
						<div class="pl-2 pr-2 pb-4" bis_skin_checked="1">
							<p class="font-bold text-sm mb-1 uppercase"></p>
							<p class="mb-2 text-[14px]">{{ $name }}<br>{{ $address }}, {{ $city }}, {{ $country }}</p>
							<div class="space-y-1 text-xs" bis_skin_checked="1">
								<p></p>
								<p class="text-[14px]"><span class="font-semibold ">Email:</span>{{ $email }}</p>
							</div>
						</div>
					</div>
					<div bis_skin_checked="1">
						<table class="w-full border-collapse border border-black text-[12px]">
							<thead>
								<tr class="bg-gray-200">
									<th class="border border-black  pl-2 pr-2 pb-2 pt-2 font-bold">Quotation Date</th>
									<th class="border border-black  pl-2 pr-2 pb-2 pt-2  font-bold">Expiry Date</th>
									<th class="border border-black  pl-2 pr-2 pb-2 pt-2  font-bold">Quotation No</th>
								</tr>
							</thead>
							<tbody>
								<tr class="text-center">
									<td class="border border-black  pl-2 pr-2 pb-2 pt-2 ">
										{{ $createdAt }}
									</td>
									<td class="border border-black  pl-2 pr-2 pb-2 pt-2  text-red-600 font-semibold">
										{{ $expiredAt }}
									</td>
									<td class="border border-black  pl-2 pr-2 pb-2 pt-2 ">
										{{ $quoteNumber }}
									</td>
								</tr>
								<tr class="bg-gray-200 text-center">
									<td class="border border-black  pl-2 pr-2 pb-2 pt-2  font-bold">Payment Mode</td>
									<td class="border border-black  pl-2 pr-2 pb-2 pt-2  font-bold">Quotation Type</td>
									<td class="border border-black  pl-2 pr-2 pb-2 pt-2  font-bold">Currency</td>
								</tr>
								<tr class="text-center">
									<td class="border border-black  pl-2 pr-2 pb-2 pt-2 ">
										{{ $paymentMode }}
									</td>
									<td class="border border-black  pl-2 pr-2 pb-2 pt-2 ">
										{{ $email }}
									</td>
									<td class="border border-black  pl-2 pr-2 pb-2 pt-2  text-red-600 font-semibold">
										{{ $currency }}
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

				<div class="p-2 mt-1" bis_skin_checked="1" style="flex-grow: 1;">
					<table class="w-full border-collapse border border-black text-[11px]">
						<thead>
							<tr class="bg-gray-200">
								<th class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2  font-bold w-[5%]">
									S.No
								</th>
								<th class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 font-bold w-[35%]">
									Description
								</th>
								<th class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 font-bold w-[10%]">
									Image
								</th>
								<th class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 font-bold w-[8%]">
									Quantity
								</th>
								<th class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 font-bold w-[8%]">
									UNIT
								</th>
								<th class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 font-bold w-[10%]">
									Unit Price
								</th>
								<th class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 font-bold w-[12%]">
									Total
								</th>
							</tr>
						</thead>
						<tbody>
							@foreach($products as $product)
							<tr class="bg-white">
								<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">
									01{{ $product->count }}
								</td>
								<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2">
									<div class="space-y-0.5" bis_skin_checked="1">
										<p class="font-bold text-[12px]">
											{{ Str::limit($product->name, 90, '...') }}
										</p>
										<p class="text-[11px]">
											<span class="font-bold">Brand:</span>
											<span class="text-red-600">{{ $product->brandName }}</span> |
											<span class="font-bold">SKU #:</span>
											<span class="text-red-600">{{ $product->sku }}</span>
										</p>
										<p class="text-[11px] pb-[3px]">
											<span class="font-bold">Warranty :</span>
											<span class="text-red-600">{{ $product->warrantyInfo }}</span>
										</p>
										<p class="text-[11px] pb-[3px]">
											<span class="font-bold text-[#1B6738]">{{ $product->shippingCharge }}</span>
											<span class="font-bold">Mostly Ships in {{ $product->deliveryDays }}</span>
										</p>
										<a href="{{ $product->productURL }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 text-[9.7px] ">
											Click here for more details
										</a>
									</div>
								</td>
								<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center align-middle">
									<div class="flex justify-center items-center h-full" bis_skin_checked="1">
										<img src="https://pim.thehorecastore.co/api/proxy-image?url=https%3A%2F%2Fhorecastore-s3-storage.s3.us-west-1.amazonaws.com%2Fproduction%2Fproducts%2FSF34HC-S_6899c36e-e0fe-4312-800a-b2eb22d3355e.webp" class="w-12 h-12 object-contain" crossorigin="anonymous" alt="Product{{ $product->image }}">
									</div>
								</td>
								<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">{{ $product->quantity }}</td>
								<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">{{ $product->sellingType }}</td>
								<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">{{ $product->unitPrice }}</td>
								<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">{{ $product-> total}}</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>

				<div bis_skin_checked="1">
					<div class="pb-[20px]" bis_skin_checked="1">
						<div class="border-t border-black p-2 mt-[10px] pb-[-100px] text-xs flex flex-col items-center gap-2" bis_skin_checked="1">
							<div class="w-full flex justify-between" bis_skin_checked="1">
								<div bis_skin_checked="1">Order Online for Fast Shipping & Lower Prices at
									<a href="{{ $siteURL }}" target="_blank" rel="noopener noreferrer" class="text-green-700">
										$siteURL
									</a>
								</div>
								<div bis_skin_checked="1">Page 1 of 3//dynamic</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div bis_skin_checked="1">
			<div bis_skin_checked="1" style="break-after: page;"></div>
			<div class="col-span-12  mt-2" bis_skin_checked="1" style="height: 1050px; box-sizing: border-box; padding: 50px;">
				<div class="flex flex-col justify-between h-full" bis_skin_checked="1">

					<div class="grid grid-cols-3 gap-4 p-2" bis_skin_checked="1">
						<div class="flex items-center" bis_skin_checked="1">
							<img class="h-30 w-40" src="{{ $logoUrl }}" alt="logo">
						</div>
						<div class="text-center" bis_skin_checked="1">
							<h1 class="text-[16px] font-bold">SALES QUOTATION</h1>
							<p class="text-[13px] font-bold text-[#186737]">Best Price. Zero Hassle.</p>
						</div>
						<div class="text-right text-[11px]" bis_skin_checked="1">
							<p class="font-bold">{{ $companyName }}.</p>
							<p>{{ $street }}</p>
							<p>{{ $city }}</p>
							<p>Phone: {{ $phone }}</p>
							<p>Email: {{ $siteEmail }}</p>
							<p>{{ $siteURL }}</p>
						</div>
					</div>

					<div class="p-[15px]" bis_skin_checked="1">
						<table class="w-full border border-gray-300 text-[11px]">
							<thead>
								<tr class="bg-gray-200 text-center font-semibold">
									<th class="border border-black pl-2 pr-2 pb-2 pt-2 ">Quotation Date</th>
									<th class="border border-black pl-2 pr-2 pb-2 pt-2 ">Expiry Date</th>
									<th class="border border-black pl-2 pr-2 pb-2 pt-2 ">Quotation No</th>
									<th class="border border-black pl-2 pr-2 pb-2 pt-2 ">Paymen Mode</th>
									<th class="border border-black pl-2 pr-2 pb-2 pt-2 ">Quotation Type</th>
									<th class="border border-black pl-2 pr-2 pb-2 pt-2 ">Currency</th>
								</tr>
							</thead>
							<tbody>
								<tr class="text-center">
									<td class="border border-black pl-2 pr-2 pb-2 pt-2 ">{{ $createdAt }}</td>
									<td class="border border-black text-[#FF0000] pl-2 pr-2 pb-2 pt-2 ">{{ $expiredAt }}</td>
									<td class="border border-black pl-2 pr-2 pb-2 pt-2 ">{{ $quoteNumber }}</td>
									<td class="border border-black pl-2 pr-2 pb-2 pt-2 ">{{ $paymentMode }}</td>
									<td class="border border-black pl-2 pr-2 pb-2 pt-2 ">{{ $email }}</td>
									<td class="border border-black text-[#FF0000] pl-2 pr-2 pb-2 pt-2 ">{{ $currency }}</td>
								</tr>
							</tbody>
						</table>
					</div>
					<div class="p-[15px] mt-[10px]" bis_skin_checked="1" style="flex-grow: 1;">
						<table class="w-full border-collapse border border-black text-[11px]">
							<thead>
								<tr class="bg-gray-200">
									<th class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2  font-bold w-[5%]">S.No</th>
									<th class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 font-bold w-[35%]">Description</th>
									<th class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 font-bold w-[10%]">Image</th>
									<th class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 font-bold w-[8%]">Quantity</th>
									<th class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 font-bold w-[8%]">UNIT</th>
									<th class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 font-bold w-[10%]">Unit Price</th>
									<th class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 font-bold w-[12%]">Total</th>
								</tr>
							</thead>
							<tbody>
								<tr class="bg-white">
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">7</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2">
										<div class="space-y-0.5" bis_skin_checked="1">
											<p class="font-bold text-[12px]">
												Reach-In Refrigerator, 48", 2 Doors, 36 Cu.Ft., Stainless Steel, 6-Year Warrant...
											</p>
											<p class="text-[11px]">
												<span class="font-bold">Brand:</span>
												<span class="text-red-600">WestLake</span> |
												<span class="font-bold">SKU#:</span>
												<span class="text-red-600">WK-48R</span>
											</p>
											<p class="text-[11px] pb-[3px]">
												<span class="font-bold">Warranty :</span>
												<span class="text-red-600"> 5 Years Parts &amp; Labor, 7 Year Compresso</span>
											</p>
											<p class="text-[11px] pb-[3px]"><span class="font-bold text-[#1B6738]">FREE SHIPPING </span>
												<span class="font-bold">Mostly Ships in 2 to 3 Days</span>
											</p>
											<a href="/product/9856" target="_blank" rel="noopener noreferrer" class="text-blue-600 text-[9.7px]">
												Click here for more details
											</a>
										</div>
									</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center align-middle">
										<div class="flex justify-center items-center h-full" bis_skin_checked="1">
											<img src="https://pim.thehorecastore.co/api/proxy-image?url=https%3A%2F%2Fhorecastore-s3-storage.s3.us-west-1.amazonaws.com%2Fproduction%2Fproducts%2Fimages%2FWK-48R_nP87jNk2AQYUYsD5foMC.webp" class="w-12 h-12 object-contain" crossorigin="anonymous" alt="Reach-In Refrigerator, 48&quot;, 2 Doors, 36 Cu. Ft., Stainless Steel, 6-Year Warranty">
										</div>
									</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">1</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">Each</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">1,799.00</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">1,799.00</td>
								</tr>
							</tbody>
						</table>
					</div>

					<div class="border-t border-black p-2 mt-[10px] pb-[-100px] text-xs flex flex-col items-center gap-2" bis_skin_checked="1">
						<div class="w-full flex justify-between" bis_skin_checked="1">
							<div bis_skin_checked="1">Order Online for Fast Shipping & Lower Prices at
								<a href="{{ $siteURL }}" target="_blank" rel="noopener noreferrer" class="text-green-700">
									$siteURL
								</a>
							</div>
							<div bis_skin_checked="1">Page 2 of 3//dynamic</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div bis_skin_checked="1" style="break-after: page;"></div>

		<div class="col-span-12 mt-2" bis_skin_checked="1" style="height: 1075px; padding: 50px; box-sizing: border-box;">
			<div class="flex flex-col justify-between h-full" bis_skin_checked="1">
				<div class="grid grid-cols-3 gap-4 p-2" bis_skin_checked="1">
					<div class="flex items-center" bis_skin_checked="1">
						<img class="h-30 w-40" src="{{ $logoUrl }}" alt="logo">
					</div>
					<div class="text-center" bis_skin_checked="1">
						<h1 class="text-[16px] font-bold">SALES QUOTATION</h1>
						<p class="text-[13px] font-bold text-[#186737]">Best Price. Zero Hassle.</p>
					</div>
					<div class="text-right text-[11px]" bis_skin_checked="1">
						<p class="font-bold">{{ $companyName }}.</p>
						<p>{{ $street }}</p>
						<p>{{ $city }}</p>
						<p>Phone: {{ $phone }}</p>
						<p>Email: {{ $siteEmail }}</p>
						<p>{{ $siteURL }}</p>
					</div>
				</div>

				<div class="p-[15px]" bis_skin_checked="1">
					<table class="w-full border border-gray-300 text-[11px]">
						<thead>
							<tr class="bg-gray-200 text-center font-semibold">
								<th class="border border-black pl-2 pr-2 pb-2 pt-2 ">Quotation Date</th>
								<th class="border border-black pl-2 pr-2 pb-2 pt-2 ">Expiry Date</th>
								<th class="border border-black pl-2 pr-2 pb-2 pt-2 ">Quotation No</th>
								<th class="border border-black pl-2 pr-2 pb-2 pt-2 ">Paymen Mode</th>
								<th class="border border-black pl-2 pr-2 pb-2 pt-2 ">Quotation Type</th>
								<th class="border border-black pl-2 pr-2 pb-2 pt-2 ">Currency</th>
							</tr>
						</thead>
						<tbody>
							<tr class="text-center">
								<td class="border border-black pl-2 pr-2 pb-2 pt-2 ">{{ $createdAt }}</td>
								<td class="border border-black text-[#FF0000] pl-2 pr-2 pb-2 pt-2 ">{{ $expiredAt }}</td>
								<td class="border border-black pl-2 pr-2 pb-2 pt-2 ">{{ $quoteNumber }}</td>
								<td class="border border-black pl-2 pr-2 pb-2 pt-2 ">{{ $paymentMode }}</td>
								<td class="border border-black pl-2 pr-2 pb-2 pt-2 ">{{ $email }}</td>
								<td class="border border-black text-[#FF0000] pl-2 pr-2 pb-2 pt-2 ">{{ $currency }}</td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="grid grid-cols-[3fr_2fr] p-[15px] gap-4  " bis_skin_checked="1">
					<div class="border border-black p-4 bg-white" bis_skin_checked="1">
						<p class="text-[12px] font-semibold">TERMS OF SALE</p>
						<ul class="mt-2 space-y-2 text-[12px]">
							<li class="flex items-start">
								<span class="text-[13px] mr-2 mt-[-1px]">•</span>
								<span>Kindly include our Order No & Date while processing the payment through bank transfer.</span>
							</li>
							<li class="flex items-start">
								<span class="text-[13px] mr-2 mt-[-1px]">•</span>
								<span>Stock levels change daily; availability confirmed only at the point of purchase with valid LPO or Advance Payment.</span>
							</li>
							<li class="flex items-start">
								<span class="text-[13px] mr-2 mt-[-1px]">•</span>
								<span>Lead times are from the receipt of payment unless agreed otherwise.</span>
							</li>
							<li class="flex items-start">
								<span class="text-[13px] mr-2 mt-[-1px]">•</span>
								<span>Lead times are based on manufacturing times and may be subject to change.</span>
							</li>
							<li class="flex items-start">
								<span class="text-[13px] mr-2 mt-[-1px]">•</span>
								<span>Once items are available, delivery must be accepted/received within 2 weeks.</span>
							</li>
							<li class="flex items-start">
								<span class="text-[13px] mr-2 mt-[-1px]">•</span>
								<span>If delivery is delayed by the customer, storage charges may apply. Installation not
								included unless agreed.</span>
							</li>
						</ul>
					</div>

					<div class=" border border-black  bg-white ml-[-20px]" bis_skin_checked="1">
						<div class="p-4" bis_skin_checked="1">
							<table class="w-full text-xs">
								<tbody class="text-[12px]">
									<tr>
										<td class="text-left py-1 font-semibold">INVOICE SUBTOTAL</td>
										<td class="text-right py-1">{{ $subTotal }}</td>
									</tr>
									<tr>
										<td class="text-left py-1 font-semibold">SHIPPING CHARGE</td>
										<td class="text-right py-1">{{ $shippingCharge }}</td>
									</tr>
									<tr>
										<td class="text-left py-1 font-semibold">{{ $taxName }} ({{ $taxPercent }}%)</td>
										<td class="text-right py-1">{{ $taxAmount }}</td>
									</tr>
								</tbody>
							</table>
						</div>
						<p class="flex  justify-between text-[#FF0000] bg-[#E7E7E7] pl-2 pr-2 pb-2 pt-2  font-semibold">
							<span>NET TOTAL INCL. {{ $taxName }}</span><span>{{ $total }}</span>
						</p>
						<div class="text-right pl-4 pt-2 pb-2 pr-2" bis_skin_checked="1">
							<p class="font-semibold">{{ $totalInWords }}</p>
						</div>
					</div>
				</div>

				<div class="mt-[0px] mb-[-50px]" bis_skin_checked="1">
					<div class="grid grid-cols-2 gap-4 p-[15px] mb-[80px]" bis_skin_checked="1">
						<div class="border border-black bg-white" bis_skin_checked="1">
							<div class="bg-gray-200 text-center px-2 pb-2 pt-2" bis_skin_checked="1">
								<p class="font-semibold text-md">Bank Details</p>
							</div>
							<div bis_skin_checked="1">
								<table class="w-full border-collapse text-[12px]">
									<tbody>
										<tr>
											<td class="bg-[#E7E7E7] border-t border-black font-semibold whitespace-nowrap pl-2 pr-2 pb-2  pt-2">
												Account Name
											</td>
											<td class="border-t border-black pl-1 pr-2 pb-4 pt-1 font-semibold">
												{{ $companyName }}
											</td>
										</tr>
										<tr>
											<td class="bg-[#E7E7E7] border-t border-black font-semibold whitespace-nowrap pl-2 pr-2 pb-2  pt-2">
												Beneficiary Address
											</td>
											<td class="border-t border-black pl-1 pr-2 pb-4 pt-1 font-semibold">
												{{ $beneficiaryAddress }}
											</td>
										</tr>
										<tr>
											<td class="bg-[#E7E7E7] border-t border-black font-semibold whitespace-nowrap pl-2 pr-2 pb-2  pt-2">
												Account No
											</td>
											<td class="border-t border-black pl-1 pr-2 pb-4 pt-1 font-semibold">
												{{ $accountNo }}
											</td>
										</tr>
										<tr>
											<td class="bg-[#E7E7E7] border-t border-black font-semibold whitespace-nowrap pl-2 pr-2 pb-2  pt-2">
												Bank
											</td>
											<td class="border-t border-black pl-1 pr-2 pb-4 pt-1 font-semibold">
												{{ $bankName }}
											</td>
										</tr>
										<tr>
											<td class="bg-[#E7E7E7] border-t border-black font-semibold whitespace-nowrap pl-2 pr-2 pb-2  pt-2">
												Routing Code
											</td>
											<td class="border-t border-black pl-1 pr-2 pb-4 pt-1 font-semibold">
												{{ $routingCode }}
											</td>
										</tr>
										<tr>
											<td class="bg-[#E7E7E7] border-t border-black pl-1 pr-2 pb-5 pt-1 font-semibold whitespace-nowrap">
												In Case Of Cheque Payment
											</td>
											<td class="border-t border-black pl-1 pr-2 pb-5 pt-1 font-semibold whitespace-nowrap">
												Please prepare all cheques in favor of <br>
												<span class="text-[#FF0000] block">{{ $companyName }}</span>
											</td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>

						<div class="border  border-black bg-[white]" bis_skin_checked="1">
							<div class="bg-gray-200 text-center pl-2 pr-2 pb-2 pt-2" bis_skin_checked="1">
								<p class="font-semibold text-md">
									Customer Payment Terms & Order Processing Timeline
								</p>
							</div>
							<div class="pl-1 pb-2  text-[12px] leading-relaxed" bis_skin_checked="1">
								<p>
									<strong>Bank Transfer (Wire/Local):</strong>
									Orders are processed after 2–3 business days upon receipt of funds.
								</p>
								<p>
									<strong>ACH Payments:</strong>
									Orders are processed after 3 business days from payment receipt.
								</p>
								<p>
									<strong>Personal Checks:</strong>
									Orders are processed after 5 business days from deposit date.
								</p>
								<p>
									<strong>Corporate Checks:</strong>
									Orders are processed after 3 business days from deposit date.
								</p>
								<p>
									<strong>Cash & Card Payments:</strong>
									Orders are processed immediately upon successful payment.
								</p>
								<p>
									<strong>Online Payments (Credit/Debit):</strong>
									Subject to fraud screening—may take 1–2 extra business days if flagged.
								</p>
								<p>
									<strong>Confirmation:</strong>
									Orders are confirmed only after payment clearance and fraud checks are completed.
								</p>
							</div>
						</div>
					</div>
					<p class="font-semibold text-center mb-5">
						This is a system generated Invoice. Hence, no stamp or signature required.
					</p>
				</div>

				<div class="border-t border-black p-2 mt-[10px] pb-[-100px] text-xs flex flex-col items-center gap-2" bis_skin_checked="1">
					<div class="w-full flex justify-between" bis_skin_checked="1">
						<div bis_skin_checked="1">Order Online for Fast Shipping & Lower Prices at
							<a href="{{ $siteURL }}" target="_blank" rel="noopener noreferrer" class="text-green-700">
								$siteURL
							</a>
						</div>
						<div bis_skin_checked="1">Page 3 of 3//dynamic</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</body>

</html>