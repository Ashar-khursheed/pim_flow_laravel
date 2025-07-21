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

<body>
	<div id="targetDiv" bis_skin_checked="1" style="width: auto; min-height: 290mm; margin: 0px auto; padding: 5mm; font-size: 12px; line-height: 1.3; font-family: Outfit; background-color: white;">
		<div class="bg-white" bis_skin_checked="1"
		style="min-height: 1070px; height: 1070px; display: flex; flex-direction: column; justify-content: space-between; padding: 50px; box-sizing: border-box;">
		<div class="flex  flex-col justify-between h-full" bis_skin_checked="1">
			<div class="grid grid-cols-3 gap-4 p-2" bis_skin_checked="1">
				<div class="flex items-center" bis_skin_checked="1">
					<img class="h-30 w-40" src="/images/horecalogo.png" alt="logo">
				</div>
				<div class="text-center" bis_skin_checked="1">
					<h1 class="text-[16px] font-bold">SALES QUOTATION</h1>
					<p class="text-[13px] font-bold text-[#186737]">Best Price. Zero Hassle.</p>
				</div>
				<div class="text-right text-[11px]" bis_skin_checked="1">
					<p class="font-bold">THE HORECA STORE INC.</p>
					<p>8800 Bissonnet Street, Ste A,</p>
					<p>Houston, Texas 77074</p>
					<p>Phone: 1 (866) 446-7322</p>
					<p>Email: sales@thehorecastore.com</p>
					<p>www.thehorecastore.com</p>
				</div>
			</div>
			<div class="grid grid-cols-2 gap-4 p-2" bis_skin_checked="1">
				<div class="border border-black" bis_skin_checked="1">
					<div class="bg-gray-200  pl-2 pr-2 pb-2 pt-2 text-center font-semibold text-sm" bis_skin_checked="1">
						Prepared For
					</div>
					<div class="pl-2 pr-2 pb-4" bis_skin_checked="1">
						<p class="font-bold text-sm mb-1 uppercase"></p>
						<p class="mb-2 text-[14px]">Rosenberg<br>Rosenberg, Texas, United States</p>
						<div class="space-y-1 text-xs" bis_skin_checked="1">
							<p></p>
							<p class="text-[14px]"><span class="font-semibold ">Email:</span> rishi694076@gmail.com</p>
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
								<td class="border border-black  pl-2 pr-2 pb-2 pt-2 "> Jul 21 2025</td>
								<td class="border border-black  pl-2 pr-2 pb-2 pt-2  text-red-600 font-semibold">
									Jul 28 2025
								</td>
								<td class="border border-black  pl-2 pr-2 pb-2 pt-2 ">345993</td>
							</tr>
							<tr class="bg-gray-200 text-center">
								<td class="border border-black  pl-2 pr-2 pb-2 pt-2  font-bold">Payment Mode</td>
								<td class="border border-black  pl-2 pr-2 pb-2 pt-2  font-bold">Quotation Type</td>
								<td class="border border-black  pl-2 pr-2 pb-2 pt-2  font-bold">Currency</td>
							</tr>
							<tr class="text-center">
								<td class="border border-black  pl-2 pr-2 pb-2 pt-2 ">Bank Transfer/Credit Card</td>
								<td class="border border-black  pl-2 pr-2 pb-2 pt-2 ">Online</td>
								<td class="border border-black  pl-2 pr-2 pb-2 pt-2  text-red-600 font-semibold">$</td>
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
						<tr class="bg-white">
							<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">
								01
							</td>
							<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2">
								<div class="space-y-0.5" bis_skin_checked="1">
									<p class="font-bold text-[12px]">
										Beverage-Air SF34HC-S 35" Stainless Steel Shallow Well Bottle Cooler
									</p>
									<p class="text-[11px]">
										<span class="font-bold">Brand:</span>
										<span class="text-red-600">Beverage-Air</span> |
										<span class="font-bold">SKU #:</span>
										<span class="text-red-600">SF34HC-S</span>
									</p>
									<p class="text-[11px] pb-[3px]">
										<span class="font-bold">Warranty :</span>
										<span class="text-red-600"> 5 Years Parts &amp; Labor, 7 Year Compresso</span>
									</p>
									<p class="text-[11px] pb-[3px]">
										<span class="font-bold text-[#1B6738]">FREE SHIPPING </span>
										<span class="font-bold">Mostly Ships in 5 to 7 Days</span>
									</p>
									<a href="/product/21657" target="_blank" rel="noopener noreferrer" class="text-blue-600 text-[9.7px] ">
										Click here for more details
									</a>
								</div>
							</td>
							<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center align-middle">
								<div class="flex justify-center items-center h-full" bis_skin_checked="1">
									<img src="https://pim.thehorecastore.co/api/proxy-image?url=https%3A%2F%2Fhorecastore-s3-storage.s3.us-west-1.amazonaws.com%2Fproduction%2Fproducts%2FSF34HC-S_6899c36e-e0fe-4312-800a-b2eb22d3355e.webp" class="w-12 h-12 object-contain" crossorigin="anonymous" alt="Product">
								</div>
							</td>
							<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">1</td>
							<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">Each</td>
							<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">3,782.35</td>
							<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">3,782.35</td>
						</tr>
						<tr class="bg-white">
							<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">02</td>
							<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2">
								<div class="space-y-0.5" bis_skin_checked="1">
									<p class="font-bold text-[12px]">
										True TD-80-30-HC 80 1/8" TD/GC Series Galvanized Steel Forced Air Bottle Cooler ...
									</p>
									<p class="text-[11px]">
										<span class="font-bold">Brand:</span>
										<span class="text-red-600">True Refrigeration</span> |
										<span class="font-bold">SKU #:</span>
										<span class="text-red-600">TD-80-30-HC</span>
									</p>
									<p class="text-[11px] pb-[3px]">
										<span class="font-bold">Warranty :</span>
										<span class="text-red-600"> 5 Years Parts &amp; Labor, 7 Year Compresso</span>
									</p>
									<p class="text-[11px] pb-[3px]">
										<span class="font-bold text-[#1B6738]">FREE SHIPPING </span>
										<span class="font-bold">Mostly Ships in 5 to 7 Days</span>
									</p>
									<a href="/product/31546" target="_blank" rel="noopener noreferrer" class="text-blue-600 text-[9.7px] ">
										Click here for more details
									</a>
								</div>
							</td>
							<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center align-middle">
								<div class="flex justify-center items-center h-full" bis_skin_checked="1">
									<img src="https://pim.thehorecastore.co/api/proxy-image?url=https%3A%2F%2Fhorecastore-s3-storage.s3.us-west-1.amazonaws.com%2Fproduction%2Fproducts%2FTD-80-30-HC_4124c01d-5b87-49e4-94c4-e3504465404b.webp" class="w-12 h-12 object-contain" crossorigin="anonymous" alt="True TD-80-30-HC 80 1/8&quot; TD/GC Series Galvanized Steel Forced Air Bottle Cooler Holds (720) 12 oz Bottles, Lid Locks, Black, 115v/1ph">
								</div>
							</td>
							<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">1</td>
							<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">Each</td>
							<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">4,299.10</td>
							<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">4,299.10</td>
						</tr>
					</tbody>
				</table>
			</div>
			<div bis_skin_checked="1">
				<div class="pb-[20px]" bis_skin_checked="1">
					<div class="border-t border-black p-2 mt-[10px] pb-[-100px] text-xs flex flex-col items-center gap-2" bis_skin_checked="1">
					<div class="w-full flex justify-between" bis_skin_checked="1">
						<div bis_skin_checked="1">Order Online for Fast Shipping &amp; Lower Prices at
							<a href="https://www.thehorecastore.com" target="_blank" rel="noopener noreferrer" class="text-green-700">
							www.thehorecastore.com
						</a>
					</div>
							<div bis_skin_checked="1">Page 1 of 3</div>
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
					<div class="flex items-center" bis_skin_checked="1"><img class="h-30 w-40"
						src="/images/horecalogo.png" alt="logo"></div>
						<div class="text-center" bis_skin_checked="1">
							<h1 class="text-[16px] font-bold">SALES QUOTATION</h1>
							<p class="text-[13px] font-bold text-[#186737]">Best Price. Zero Hassle.</p>
						</div>
						<div class="text-right text-[11px]" bis_skin_checked="1">
							<p class="font-bold">THE HORECA STORE INC.</p>
							<p>8800 Bissonnet Street, Ste A,</p>
							<p>Houston, Texas 77074</p>
							<p>Phone: 1 (866) 446-7322</p>
							<p>Email: sales@thehorecastore.com</p>
							<p>www.thehorecastore.com</p>
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
									<td class="border border-black pl-2 pr-2 pb-2 pt-2 ">Jul 21 2025</td>
									<td class="border border-black text-[#FF0000] pl-2 pr-2 pb-2 pt-2 ">Jul 28 2025</td>
									<td class="border border-black pl-2 pr-2 pb-2 pt-2 ">345993</td>
									<td class="border border-black pl-2 pr-2 pb-2 pt-2 ">Bank Transfer/Credit Card</td>
									<td class="border border-black pl-2 pr-2 pb-2 pt-2 ">Online</td>
									<td class="border border-black text-[#FF0000] pl-2 pr-2 pb-2 pt-2 ">$</td>
								</tr>
							</tbody>
						</table>
					</div>
					<div class="p-[15px] mt-[10px]" bis_skin_checked="1" style="flex-grow: 1;">
						<table class="w-full border-collapse border border-black text-[11px]">
							<thead>
								<tr class="bg-gray-200">
									<th class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2  font-bold w-[5%]">
									S.No</th>
									<th class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 font-bold w-[35%]">
									Description</th>
									<th class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 font-bold w-[10%]">
									Image</th>
									<th class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 font-bold w-[8%]">
									Quantity</th>
									<th class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 font-bold w-[8%]">
									UNIT</th>
									<th class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 font-bold w-[10%]">
									Unit Price</th>
									<th class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 font-bold w-[12%]">
									Total</th>
								</tr>
							</thead>
							<tbody>
								<tr class="bg-white">
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">7</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2">
										<div class="space-y-0.5" bis_skin_checked="1">
											<p class="font-bold text-[12px]">Reach-In Refrigerator, 48", 2 Doors, 36 Cu.
											Ft., Stainless Steel, 6-Year Warrant...</p>
											<p class="text-[11px]"><span class="font-bold">Brand:</span> <span
												class="text-red-600">WestLake</span> | <span class="font-bold">SKU
												#:</span> <span class="text-red-600">WK-48R</span></p>
												<p class="text-[11px] pb-[3px]"><span class="font-bold">Warranty :</span>
													<span class="text-red-600"> 5 Years Parts &amp; Labor, 7 Year
													Compresso</span></p>
													<p class="text-[11px] pb-[3px]"><span class="font-bold text-[#1B6738]">FREE
													SHIPPING </span> <span class="font-bold">Mostly Ships in 2 to 3
													Days</span></p><a href="/product/9856" target="_blank"
													rel="noopener noreferrer" class="text-blue-600 text-[9.7px]">Click here
												for more details</a>
											</div>
										</td>
										<td
										class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center align-middle">
										<div class="flex justify-center items-center h-full" bis_skin_checked="1"><img
											src="https://pim.thehorecastore.co/api/proxy-image?url=https%3A%2F%2Fhorecastore-s3-storage.s3.us-west-1.amazonaws.com%2Fproduction%2Fproducts%2Fimages%2FWK-48R_nP87jNk2AQYUYsD5foMC.webp"
											class="w-12 h-12 object-contain" crossorigin="anonymous"
											alt="Reach-In Refrigerator, 48&quot;, 2 Doors, 36 Cu. Ft., Stainless Steel, 6-Year Warranty">
										</div>
									</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">1</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">Each
									</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">1,799.00
									</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">1,799.00
									</td>
								</tr>
								<tr class="bg-white">
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">8</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2">
										<div class="space-y-0.5" bis_skin_checked="1">
											<p class="font-bold text-[12px]">Reach-In Freezer, 48", 2 Doors, 36 Cu. Ft.,
											Stainless Steel, 6-Year Warranty</p>
											<p class="text-[11px]"><span class="font-bold">Brand:</span> <span
												class="text-red-600">WestLake</span> | <span class="font-bold">SKU
												#:</span> <span class="text-red-600">WK-48F</span></p>
												<p class="text-[11px] pb-[3px]"><span class="font-bold">Warranty :</span>
													<span class="text-red-600"> 5 Years Parts &amp; Labor, 7 Year
													Compresso</span></p>
													<p class="text-[11px] pb-[3px]"><span class="font-bold text-[#1B6738]">FREE
													SHIPPING </span> <span class="font-bold">Mostly Ships in 2 to 3
													Days</span></p><a href="/product/9855" target="_blank"
													rel="noopener noreferrer" class="text-blue-600 text-[9.7px]">Click here
												for more details</a>
											</div>
										</td>
										<td
										class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center align-middle">
										<div class="flex justify-center items-center h-full" bis_skin_checked="1"><img
											src="https://pim.thehorecastore.co/api/proxy-image?url=https%3A%2F%2Fhorecastore-s3-storage.s3.us-west-1.amazonaws.com%2Fproduction%2Fproducts%2Fimages%2FWK-48F_zeA37FZJ5ECEtrnh8zYy.webp"
											class="w-12 h-12 object-contain" crossorigin="anonymous"
											alt="Reach-In Freezer, 48&quot;, 2 Doors, 36 Cu. Ft., Stainless Steel, 6-Year Warranty">
										</div>
									</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">1</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">Each
									</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">1,990.00
									</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">1,990.00
									</td>
								</tr>
								<tr class="bg-white">
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">9</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2">
										<div class="space-y-0.5" bis_skin_checked="1">
											<p class="font-bold text-[12px]">Reach-In Freezer, 72", 3 Doors, 54 Cu. Ft.,
											Stainless Steel, 6-Year Warranty</p>
											<p class="text-[11px]"><span class="font-bold">Brand:</span> <span
												class="text-red-600">WestLake</span> | <span class="font-bold">SKU
												#:</span> <span class="text-red-600">WK-72F</span></p>
												<p class="text-[11px] pb-[3px]"><span class="font-bold">Warranty :</span>
													<span class="text-red-600"> 5 Years Parts &amp; Labor, 7 Year
													Compresso</span></p>
													<p class="text-[11px] pb-[3px]"><span class="font-bold text-[#1B6738]">FREE
													SHIPPING </span> <span class="font-bold">Mostly Ships in 2 to 3
													Days</span></p><a href="/product/9853" target="_blank"
													rel="noopener noreferrer" class="text-blue-600 text-[9.7px]">Click here
												for more details</a>
											</div>
										</td>
										<td
										class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center align-middle">
										<div class="flex justify-center items-center h-full" bis_skin_checked="1"><img
											src="https://pim.thehorecastore.co/api/proxy-image?url=https%3A%2F%2Fhorecastore-s3-storage.s3.us-west-1.amazonaws.com%2Fproduction%2Fproducts%2Fimages%2FWK-72F_FuPDAJXfCCpDS4LD2zcw.webp"
											class="w-12 h-12 object-contain" crossorigin="anonymous"
											alt="Reach-In Freezer, 72&quot;, 3 Doors, 54 Cu. Ft., Stainless Steel, 6-Year Warranty">
										</div>
									</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">1</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">Each
									</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">2,899.00
									</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">2,899.00
									</td>
								</tr>
								<tr class="bg-white">
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">10</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2">
										<div class="space-y-0.5" bis_skin_checked="1">
											<p class="font-bold text-[12px]">Reach-In Refrigerator, 72", 3 Doors, 54 Cu.
											Ft., Stainless Steel, 6-Year Warrant...</p>
											<p class="text-[11px]"><span class="font-bold">Brand:</span> <span
												class="text-red-600">WestLake</span> | <span class="font-bold">SKU
												#:</span> <span class="text-red-600">WK-72R</span></p>
												<p class="text-[11px] pb-[3px]"><span class="font-bold">Warranty :</span>
													<span class="text-red-600"> 5 Years Parts &amp; Labor, 7 Year
													Compresso</span></p>
													<p class="text-[11px] pb-[3px]"><span class="font-bold text-[#1B6738]">FREE
													SHIPPING </span> <span class="font-bold">Mostly Ships in 2 to 3
													Days</span></p><a href="/product/9854" target="_blank"
													rel="noopener noreferrer" class="text-blue-600 text-[9.7px]">Click here
												for more details</a>
											</div>
										</td>
										<td
										class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center align-middle">
										<div class="flex justify-center items-center h-full" bis_skin_checked="1"><img
											src="https://pim.thehorecastore.co/api/proxy-image?url=https%3A%2F%2Fhorecastore-s3-storage.s3.us-west-1.amazonaws.com%2Fproduction%2Fproducts%2Fimages%2FWK-72R_GJVCept2BGsINFAm1boP.webp"
											class="w-12 h-12 object-contain" crossorigin="anonymous"
											alt="Reach-In Refrigerator, 72&quot;, 3 Doors, 54 Cu. Ft., Stainless Steel, 6-Year Warranty">
										</div>
									</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">1</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">Each
									</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">2,499.00
									</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">2,499.00
									</td>
								</tr>
								<tr class="bg-white">
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">11</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2">
										<div class="space-y-0.5" bis_skin_checked="1">
											<p class="font-bold text-[12px]">Turbo Air TBC-24SB-N6 24-7/8" Super Seluxe
											Bottle Cooler, 3.6 cu. Ft. 115/60/1</p>
											<p class="text-[11px]"><span class="font-bold">Brand:</span> <span
												class="text-red-600">Turbo Air</span> | <span class="font-bold">SKU
												#:</span> <span class="text-red-600">TBC-24SB-N6</span></p>
												<p class="text-[11px] pb-[3px]"><span class="font-bold">Warranty :</span>
													<span class="text-red-600"> 5 Years Parts &amp; Labor, 7 Year
													Compresso</span></p>
													<p class="text-[11px] pb-[3px]"><span class="font-bold text-[#1B6738]">FREE
													SHIPPING </span> <span class="font-bold">Mostly Ships in 5 to 7
													Days</span></p><a href="/product/2796" target="_blank"
													rel="noopener noreferrer" class="text-blue-600 text-[9.7px]">Click here
												for more details</a>
											</div>
										</td>
										<td
										class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center align-middle">
										<div class="flex justify-center items-center h-full" bis_skin_checked="1"><img
											src="https://pim.thehorecastore.co/api/proxy-image?url=https%3A%2F%2Fhorecastore-s3-storage.s3.us-west-1.amazonaws.com%2Fproduction%2Fproducts%2FTBC-24SB-N6_196b76cb-f68f-4bb2-a05e-e3da2f0b30b4.webp"
											class="w-12 h-12 object-contain" crossorigin="anonymous"
											alt="Turbo Air TBC-24SB-N6 24-7/8&quot; Super Seluxe Bottle Cooler, 3.6 cu. Ft. 115/60/1">
										</div>
									</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">1</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">Each
									</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">2,222.33
									</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">2,222.33
									</td>
								</tr>
								<tr class="bg-white">
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">12</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2">
										<div class="space-y-0.5" bis_skin_checked="1">
											<p class="font-bold text-[12px]">Turbo Air TBC-36SB-N6 36-3/4" Super Deluxe
											Bottle Cooler, 8.5 cu. Ft., 115/60/1</p>
											<p class="text-[11px]"><span class="font-bold">Brand:</span> <span
												class="text-red-600">Turbo Air</span> | <span class="font-bold">SKU
												#:</span> <span class="text-red-600">TBC-36SB-N6</span></p>
												<p class="text-[11px] pb-[3px]"><span class="font-bold">Warranty :</span>
													<span class="text-red-600"> 5 Years Parts &amp; Labor, 7 Year
													Compresso</span></p>
													<p class="text-[11px] pb-[3px]"><span class="font-bold text-[#1B6738]">FREE
													SHIPPING </span> <span class="font-bold">Mostly Ships in 5 to 7
													Days</span></p><a href="/product/2794" target="_blank"
													rel="noopener noreferrer" class="text-blue-600 text-[9.7px]">Click here
												for more details</a>
											</div>
										</td>
										<td
										class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center align-middle">
										<div class="flex justify-center items-center h-full" bis_skin_checked="1"><img
											src="https://pim.thehorecastore.co/api/proxy-image?url=https%3A%2F%2Fhorecastore-s3-storage.s3.us-west-1.amazonaws.com%2Fproduction%2Fproducts%2FTBC-36SB-N6_6bb5506e-9c7b-45cb-8134-5944faa67a1a.webp"
											class="w-12 h-12 object-contain" crossorigin="anonymous"
											alt="Turbo Air TBC-36SB-N6 36-3/4&quot; Super Deluxe Bottle Cooler, 8.5 cu. Ft., 115/60/1">
										</div>
									</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">1</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">Each
									</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">2,395.26
									</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">2,395.26
									</td>
								</tr>
								<tr class="bg-white">
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">13</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2">
										<div class="space-y-0.5" bis_skin_checked="1">
											<p class="font-bold text-[12px]">Turbo Air TBC-36SD-N6 36-3/4" Super Deluxe
											Bottle Cooler, 8.5 cu. Ft., 115/60/1</p>
											<p class="text-[11px]"><span class="font-bold">Brand:</span> <span
												class="text-red-600">Turbo Air</span> | <span class="font-bold">SKU
												#:</span> <span class="text-red-600">TBC-36SD-N6</span></p>
												<p class="text-[11px] pb-[3px]"><span class="font-bold">Warranty :</span>
													<span class="text-red-600"> 5 Years Parts &amp; Labor, 7 Year
													Compresso</span></p>
													<p class="text-[11px] pb-[3px]"><span class="font-bold text-[#1B6738]">FREE
													SHIPPING </span> <span class="font-bold">Mostly Ships in 5 to 7
													Days</span></p><a href="/product/2795" target="_blank"
													rel="noopener noreferrer" class="text-blue-600 text-[9.7px]">Click here
												for more details</a>
											</div>
										</td>
										<td
										class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center align-middle">
										<div class="flex justify-center items-center h-full" bis_skin_checked="1"><img
											src="https://pim.thehorecastore.co/api/proxy-image?url=https%3A%2F%2Fhorecastore-s3-storage.s3.us-west-1.amazonaws.com%2Fproduction%2Fproducts%2FTBC-36SD-N6_804ef342-b3ab-46b6-a800-d2db61ac6bdc.webp"
											class="w-12 h-12 object-contain" crossorigin="anonymous"
											alt="Turbo Air TBC-36SD-N6 36-3/4&quot; Super Deluxe Bottle Cooler, 8.5 cu. Ft., 115/60/1">
										</div>
									</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">1</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">Each
									</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">2,945.05
									</td>
									<td class="border-t border-b border-black  pl-2 pr-2 pb-2  pt-2 text-center">2,945.05
									</td>
								</tr>
							</tbody>
						</table>
					</div>
					<div class="border-t border-black p-2 mt-[10px] pb-[-100px] text-xs flex flex-col items-center gap-2"
					bis_skin_checked="1">
					<div class="w-full flex justify-between" bis_skin_checked="1">
						<div bis_skin_checked="1">Order Online for Fast Shipping &amp; Lower Prices at <a
							href="https://www.thehorecastore.com" target="_blank" rel="noopener noreferrer"
							class="text-green-700">www.thehorecastore.com</a></div>
							<div bis_skin_checked="1">Page 2 of 3</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div bis_skin_checked="1" style="break-after: page;"></div>
		<div class="col-span-12 mt-2" bis_skin_checked="1"
		style="height: 1075px; padding: 50px; box-sizing: border-box;">
		<div class="flex flex-col justify-between h-full" bis_skin_checked="1">
			<div class="grid grid-cols-3 gap-4 p-2" bis_skin_checked="1">
				<div class="flex items-center" bis_skin_checked="1"><img class="h-30 w-40"
					src="/images/horecalogo.png" alt="logo"></div>
					<div class="text-center" bis_skin_checked="1">
						<h1 class="text-[16px] font-bold">SALES QUOTATION</h1>
						<p class="text-[13px] font-bold text-[#186737]">Best Price. Zero Hassle.</p>
					</div>
					<div class="text-right text-[11px]" bis_skin_checked="1">
						<p class="font-bold">THE HORECA STORE INC.</p>
						<p>8800 Bissonnet Street, Ste A,</p>
						<p>Houston, Texas 77074</p>
						<p>Phone: 1 (866) 446-7322</p>
						<p>Email: sales@thehorecastore.com</p>
						<p>www.thehorecastore.com</p>
					</div>
				</div>
				<div class="p-[15px]" bis_skin_checked="1">
					<table class="w-full border border-gray-300 text-[11px]">
						<thead>
							<tr class="bg-gray-200 text-center font-semibold">
								<th class="border border-black pl-2 pr-2 pb-2 pt-2 ">Quotation Date</th>
								<th class="border border-black pl-2 pr-2 pb-2 pt-2 ">Expiry Date</th>
								<th class="border border-black pl-2 pr-2 pb-2 pt-2 ">Quotation No</th>
								<th class="border border-black pl-2 pr-2 pb-2 pt-2 ">Payment Mode</th>
								<th class="border border-black pl-2 pr-2 pb-2 pt-2 ">Quotation Type</th>
								<th class="border border-black pl-2 pr-2 pb-2 pt-2 ">Currency</th>
							</tr>
						</thead>
						<tbody>
							<tr class="text-center">
								<td class="border border-black pl-2 pr-2 pb-2 pt-2 ">Jul 21 2025</td>
								<td class="border border-black text-[#FF0000] pl-2 pr-2 pb-2 pt-2 ">Jul 28 2025</td>
								<td class="border border-black pl-2 pr-2 pb-2 pt-2 ">345993</td>
								<td class="border border-black pl-2 pr-2 pb-2 pt-2 ">Bank Transfer/Credit Card</td>
								<td class="border border-black pl-2 pr-2 pb-2 pt-2 ">Online</td>
								<td class="border border-black text-[#FF0000] pl-2 pr-2 pb-2 pt-2 ">$</td>
							</tr>
						</tbody>
					</table>
				</div>
				<div class="grid grid-cols-[3fr_2fr] p-[15px] gap-4  " bis_skin_checked="1">
					<div class="border border-black p-4 bg-white" bis_skin_checked="1">
						<p class="text-[12px] font-semibold">TERMS OF SALE</p>
						<ul class="mt-2 space-y-2 text-[12px]">
							<li class="flex items-start"><span class="text-[13px] mr-2 mt-[-1px]">•</span><span>Kindly
								include our Order No &amp; Date while processing the payment through bank
							transfer.</span></li>
							<li class="flex items-start"><span class="text-[13px] mr-2 mt-[-1px]">•</span><span>Stock
								levels change daily; availability confirmed only at the point of purchase with valid
							LPO or Advance Payment.</span></li>
							<li class="flex items-start"><span class="text-[13px] mr-2 mt-[-1px]">•</span><span>Lead
							times are from the receipt of payment unless agreed otherwise.</span></li>
							<li class="flex items-start"><span class="text-[13px] mr-2 mt-[-1px]">•</span><span>Lead
							times are based on manufacturing times and may be subject to change.</span></li>
							<li class="flex items-start"><span class="text-[13px] mr-2 mt-[-1px]">•</span><span>Once
							items are available, delivery must be accepted/received within 2 weeks.</span></li>
							<li class="flex items-start"><span class="text-[13px] mr-2 mt-[-1px]">•</span><span>If
								delivery is delayed by the customer, storage charges may apply. Installation not
							included unless agreed.</span></li>
						</ul>
					</div>
					<div class=" border border-black  bg-white ml-[-20px]" bis_skin_checked="1">
						<div class="p-4" bis_skin_checked="1">
							<table class="w-full text-xs">
								<tbody class="text-[12px]">
									<tr>
										<td class="text-left py-1 font-semibold">INVOICE SUBTOTAL</td>
										<td class="text-right py-1">32,152.09</td>
									</tr>
									<tr>
										<td class="text-left py-1 font-semibold">TOTAL W/O TAX</td>
										<td class="text-right py-1">32,152.09</td>
									</tr>
									<tr>
										<td class="text-left py-1 font-semibold">SALES TAX (2.9000000000000004%)</td>
										<td class="text-right py-1">932.41</td>
									</tr>
								</tbody>
							</table>
						</div>
						<p class="flex  justify-between text-[#FF0000] bg-[#E7E7E7] pl-2 pr-2 pb-2 pt-2  font-semibold">
							<span>NET TOTAL INCL. SALES TAX</span><span>33,084.50</span></p>
							<div class="text-right pl-4 pt-2 pb-2 pr-2" bis_skin_checked="1">
								<p class="font-semibold">Thirty-Three Thousand, Eighty-Four And 50/100 U.S. Dollars Only</p>
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
												<td
												class="bg-[#E7E7E7] border-t border-black font-semibold whitespace-nowrap pl-2 pr-2 pb-2  pt-2">
											Account Name</td>
											<td class="border-t border-black pl-1 pr-2 pb-4 pt-1 font-semibold">THE
											HORECA STORE INC</td>
										</tr>
										<tr>
											<td
											class="bg-[#E7E7E7] border-t border-black font-semibold whitespace-nowrap pl-2 pr-2 pb-2  pt-2">
										Beneficiary Address</td>
										<td class="border-t border-black pl-1 pr-2 pb-4 pt-1 font-semibold">8800
										BISSONNET ST STE A, HOUSTON TX 77074-2435</td>
									</tr>
									<tr>
										<td
										class="bg-[#E7E7E7] border-t border-black font-semibold whitespace-nowrap pl-2 pr-2 pb-2  pt-2">
									Account No</td>
									<td class="border-t border-black pl-1 pr-2 pb-4 pt-1 font-semibold">6130
									9953 3</td>
								</tr>
								<tr>
									<td
									class="bg-[#E7E7E7] border-t border-black font-semibold whitespace-nowrap pl-2 pr-2 pb-2  pt-2">
								Bank</td>
								<td class="border-t border-black pl-1 pr-2 pb-4 pt-1 font-semibold">JP
								Morgan Chase Bank</td>
							</tr>
							<tr>
								<td
								class="bg-[#E7E7E7] border-t border-black font-semibold whitespace-nowrap pl-2 pr-2 pb-2  pt-2">
							Routing Code</td>
							<td class="border-t border-black pl-1 pr-2 pb-4 pt-1 font-semibold">1110
							0061 4</td>
						</tr>
						<tr>
							<td
							class="bg-[#E7E7E7] border-t border-black pl-1 pr-2 pb-5 pt-1 font-semibold whitespace-nowrap">
						In Case Of Cheque Payment</td>
						<td
						class="border-t border-black pl-1 pr-2 pb-5 pt-1 font-semibold whitespace-nowrap">
						Please prepare all cheques in favor of <br><span
						class="text-[#FF0000] block">THE HORECA STORE INC</span></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
	<div class="border  border-black bg-[white]" bis_skin_checked="1">
		<div class="bg-gray-200 text-center pl-2 pr-2 pb-2 pt-2" bis_skin_checked="1">
			<p class="font-semibold text-md">Customer Payment Terms &amp; Order Processing Timeline
			</p>
		</div>
		<div class="pl-1 pb-2  text-[12px] leading-relaxed" bis_skin_checked="1">
			<p><strong>Bank Transfer (Wire/Local):</strong> Orders are processed after 2–3 business
			days upon receipt of funds.</p>
			<p><strong>ACH Payments:</strong> Orders are processed after 3 business days from
			payment receipt.</p>
			<p><strong>Personal Checks:</strong> Orders are processed after 5 business days from
			deposit date.</p>
			<p><strong>Corporate Checks:</strong> Orders are processed after 3 business days from
			deposit date.</p>
			<p><strong>Cash &amp; Card Payments:</strong> Orders are processed immediately upon
			successful payment.</p>
			<p><strong>Online Payments (Credit/Debit):</strong> Subject to fraud screening—may take
			1–2 extra business days if flagged.</p>
			<p><strong>Confirmation:</strong> Orders are confirmed only after payment clearance and
			fraud checks are completed.</p>
		</div>
	</div>
</div>
<p class="font-semibold text-center mb-5">This is a system generated Invoice. Hence, no stamp or
signature required.</p>
</div>
<div class="border-t border-black p-2 mt-[10px] pb-[-100px] text-xs flex flex-col items-center gap-2"
bis_skin_checked="1">
<div class="w-full flex justify-between" bis_skin_checked="1">
	<div bis_skin_checked="1">Order Online for Fast Shipping &amp; Lower Prices at <a
		href="https://www.thehorecastore.com" target="_blank" rel="noopener noreferrer"
		class="text-green-700">www.thehorecastore.com</a></div>
		<div bis_skin_checked="1">Page 3 of 3</div>
	</div>
</div>
</div>
</div>
</div>
</body>

</html>