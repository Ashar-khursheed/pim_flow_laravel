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
$totalProducts = $products->count();
$pattern = [5];

while (array_sum($pattern) < $totalProducts) {
	$pattern[] = 6;
}

foreach ($pattern as $size) {
	if ($offset >= $totalProducts) break;
	$chunks[] = $products->slice($offset, $size);
	$offset += $size;
}
$pageNumber = 1;
@endphp
<body>
	<div id="targetDiv" style="width: auto; min-height: 290mm; margin: 0px auto;  font-size: 12px; line-height: 1.3; font-family: Outfit;background-color: white;">
		<div style="min-height: 1070px; height: 1070px; display: flex; flex-direction: column; padding: 10px 0; box-sizing: border-box;background-color: white; position: relative;">
			@foreach($chunks as $index => $chunk)

			@if($index > 0)
			<div style="page-break-before: always;"></div>
			@endif

			{{-- Header --}}
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
						<p style="margin: 0;">{{ $companyStreet }}</p>
						<p style="margin: 0;">{{ $companyCity }}</p>
						<p style="margin: 0;">Phone: {{ $companyPhone }}</p>
						<p style="margin: 0;">Email: {{ $siteEmail }}</p>
						<p style="margin: 0;">{{ $siteURL }}</p>
					</td>
				</tr>
			</table>

			{{-- Customer details for first page--}}
			@if($index === 0)
			<table style="width: 100%; border-collapse: collapse; margin-bottom: 1rem; font-size: 14px; font-family: 'Inter', sans-serif;">
				<tr>
					<td style="width: 49%; border: 1px solid black; vertical-align: top; padding: 0;">
						<table style="width: 100%; border-collapse: collapse; font-size: 14px; font-family: 'Inter', sans-serif;">
							<tr>
								<td colspan="1" style="background-color: #d9d9d9; text-align: center; font-weight: 600; font-size: 14px; padding: 10px 8px 8px;">
									Prepared For({{ ucfirst(strtolower($customerType)) }})
								</td>
							</tr>
							@if($customerBusinessName)
							<tr>
								<td style="padding: 2px 0.5rem;font-weight: 700;text-transform: uppercase;margin: 0;">
									{{ $customerBusinessName }}
								</td>
							</tr>
							@endif
							<tr>
								<td style="padding: 2px 0.5rem;font-weight: 700;text-transform: uppercase;margin: 0;">
									{{ $customerName }}
								</td>
							</tr>
							<tr>
								<td style="padding: 2px 0.5rem; margin: 0;">
									{{ $customerAddress }}, {{ $customerCity }}, {{ $customerCountry }}
								</td>
							</tr>
							<tr>
								<td style="padding: 5px 0.5rem 0px; margin: 0;">
									<strong>Telephone::</strong> {{ $customerPhone }}
								</td>
							</tr>
							<tr>
								<td style="padding: 2px 0.5rem 0px; margin: 0;">
									<strong>Email:</strong> {{ $customerEmail }}
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
			@endif

			<table style="width:100%; border-collapse:collapse; border:1px solid black; font-size:11px; font-family: 'Inter', sans-serif;">
				<thead>
					<tr style="background-color:#d9d9d9;">
						<th style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; font-weight:bold; width:5%; font-family: 'Inter', sans-serif;">S.No</th>
						<th style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; font-weight:bold; width:38%; font-family: 'Inter', sans-serif;">Description</th>
						<th style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; font-weight:bold; width:10%; font-family: 'Inter', sans-serif;">Image</th>
						<th style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; font-weight:bold; width:8%; font-family: 'Inter', sans-serif;">Quantity</th>
						<th style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; font-weight:bold; width:7%; font-family: 'Inter', sans-serif;">Unit</th>
						<th style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; font-weight:bold; width:10%; font-family: 'Inter', sans-serif;">Unit Price</th>
						<th style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; font-weight:bold; width:10%; font-family: 'Inter', sans-serif;">Acc. Chg.</th>
						<th style="border-top:1px solid black; border-bottom:1px solid black; padding:8px; font-weight:bold; width:12% ; font-family: 'Inter', sans-serif;">Total</th>
					</tr>
				</thead>
				<tbody>
					@foreach($chunk as $index1 => $product)
					<tr style="background-color:white; font-family: 'Inter', sans-serif;">
						<td style="border-top:1px solid black; border-bottom:1px solid black;  text-align:center; font-family: 'Inter', sans-serif;">{{ $index1+1 }}</td>
						<td style="border-top:1px solid black; border-bottom:1px solid black;  font-family: 'Inter', sans-serif;">
							<div style="margin-bottom:4px; font-family: 'Inter', sans-serif;">
								<p style="font-weight:bold; font-size:13px;margin-top:2px; margin-bottom:0px; font-family: 'Inter', sans-serif;">
									{{ Str::limit($product->name, 70, '...') }}
								</p>
								<p style="font-size:11px; margin-top:3px; margin-bottom:0px; font-family: 'Inter', sans-serif;">
									<span style="font-weight:bold; font-family: 'Inter', sans-serif;">Brand:</span>
									<span style="color:#dc2626; font-family: 'Inter', sans-serif;">{{ Str::limit($product->brandName, 40, '...') }}</span>
								</p>
								<p style="font-size:11px; margin-top:3px; margin-bottom:0px; font-family: 'Inter', sans-serif;">
									<span style="font-weight:bold; font-family: 'Inter', sans-serif;">SKU #:</span>
									<span style="color:#dc2626; font-family: 'Inter', sans-serif;">{{ Str::limit($product->sku, 40, '...') }}</span>
								</p>
								<p style="font-size:11px; margin-top:3px; margin-bottom:0px; font-family: 'Inter', sans-serif;">
									<span style="font-weight:bold; font-family: 'Inter', sans-serif;">Warranty:</span>
									<span style="color:#dc2626; font-family: 'Inter', sans-serif;">{{ Str::limit($product->warrantyInfo, 35, '...') }}</span>
								</p>
								<p style="font-size:11px; margin-top:3px; margin-bottom:0px; font-family: 'Inter', sans-serif;">
									<span style="font-weight:bold; font-family: 'Inter', sans-serif;">Mostly Ships in {{ $product->deliveryDays }}</span>
								</p>
								<a href="{{ $product->productURL }}" target="_blank" rel="noopener noreferrer" style="margin-top:3px; margin-bottom:0px; color:#2563eb; font-size:9.7px;">
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

						<td style="border-top:1px solid black; border-bottom:1px solid black;  text-align:center;">{{ $currency }} {{ number_format($product->unitPrice, 2, '.', ',') }}</td>


						<td style="border-top:1px solid black; border-bottom:1px solid black;  text-align:center;">{{ $currency }} {{ number_format($product->accessoryCharge, 2, '.', ',') }}</td>

						<td style="border-top:1px solid black; border-bottom:1px solid black;  text-align:center;">{{ $currency }} {{ number_format($product->total, 2, '.', ',') }}</td>
					</tr>
					@endforeach
				</tbody>
			</table>

			{{-- Footer --}}
			@if(($index === 0 && in_array(count($chunk), [3, 4, 5])) || ($index > 0 && count($chunk) == 6) || ($index === count($chunks)-1 && in_array(count($chunk), [4, 5, 6])))
			<table style="width: 100%; border-top: 1px solid black; margin-top: 10px; padding-top: 2px; font-size: 12px; font-family: 'Inter', sans-serif; position: absolute; bottom: 70px;">
				<tr>
					<td style="text-align: left;">
						Order Online for Fast Shipping & Lower Prices at
						<a href="{{ $siteURL }}" target="_blank" rel="noopener noreferrer" style="color: #186737; font-family: 'Inter', sans-serif;">{{ $siteURL }}</a>
					</td>
					<td style="text-align: right; font-family: 'Inter', sans-serif;">
						Page {{ $index+1 }}
					</td>
				</tr>
			</table>
			@php($pageNumber++)
			@endif

			@endforeach


			@if(($index === 0 && in_array(count($chunk), [4, 5])) || ($index > 0 && count($chunk) == 6) || ($index > 0 && $index === count($chunks)-1 && in_array(count($chunk), [4, 5, 6])))
			@if(($index === 0 && count($chunk) == 4) || ($index === count($chunks)-1 && in_array(count($chunk), [4, 5])))
			<div style="page-break-before: always;"></div>
			@endif

			{{-- Header --}}
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
						<p style="margin: 0;">{{ $companyStreet }}</p>
						<p style="margin: 0;">{{ $companyCity }}</p>
						<p style="margin: 0;">Phone: {{ $companyPhone }}</p>
						<p style="margin: 0;">Email: {{ $siteEmail }}</p>
						<p style="margin: 0;">{{ $siteURL }}</p>
					</td>
				</tr>
			</table>
			@endif

			{{-- Amount Details --}}
			<table style="width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 15px; font-family: 'Inter', sans-serif;">
				<tr>
					<td style="width: 50%; vertical-align: top; border: 1px solid black; padding: 10px; background-color: #ffffff; font-family: 'Inter', sans-serif;">
						<p style="font-size: 12px; font-weight: 600; font-family: 'Inter', sans-serif; margin-top: 0px;">TERMS OF SALE</p>
						<ul style="font-size: 12px; list-style: none; padding: 0; font-family: 'Inter', sans-serif;">
							<li style="display: flex; align-items: flex-start; margin-top: 3px; font-family: 'Inter', sans-serif;">
								<span style="font-size: 13px; margin-right: 8px; font-family: 'Inter', sans-serif;">•</span>
								<span>Kindly include our Order No & Date while processing the payment through bank transfer.</span>
							</li>
							<li style="display: flex; align-items: flex-start; margin-top: 3px; font-family: 'Inter', sans-serif;">
								<span style="font-size: 13px; margin-right: 8px; font-family: 'Inter', sans-serif;">•</span>
								<span>Stock levels change daily; availability confirmed only at the point of purchase with valid LPO or Advance Payment.</span>
							</li>
							<li style="display: flex; align-items: flex-start; margin-top: 3px; font-family: 'Inter', sans-serif;">
								<span style="font-size: 13px; margin-right: 8px; font-family: 'Inter', sans-serif;">•</span>
								<span>Lead times are from the receipt of payment unless agreed otherwise.</span>
							</li>
							<li style="display: flex; align-items: flex-start; margin-top: 3px; font-family: 'Inter', sans-serif;">
								<span style="font-size: 13px; margin-right: 8px; font-family: 'Inter', sans-serif;">•</span>
								<span>Lead times are based on manufacturing times and may be subject to change.</span>
							</li>
							<li style="display: flex; align-items: flex-start; margin-top: 3px; font-family: 'Inter', sans-serif;">
								<span style="font-size: 13px; margin-right: 8px; font-family: 'Inter', sans-serif;">•</span>
								<span>Once items are available, delivery must be accepted/received within 2 weeks.</span>
							</li>
							<li style="display: flex; align-items: flex-start; margin-top: 3px; margin-bottom: 0px; font-family: 'Inter', sans-serif;">
								<span style="font-size: 13px; margin-right: 8px; font-family: 'Inter', sans-serif;">•</span>
								<span>If delivery is delayed by the customer, storage charges may apply. Installation not included unless agreed.</span>
							</li>
						</ul>
					</td>


					<!-- Invoice Summary Column -->
					<td style="width: 50%; vertical-align: top; border: 1px solid black; background-color: #ffffff; font-family: 'Inter', sans-serif;">
						<div style="padding: 10px; font-family: 'Inter', sans-serif;">
							<table style="width: 100%; font-size: 12px; font-family: 'Inter', sans-serif;">
								<tbody>
									@if (isset($additionalAmountName) && isset($additionalAmountPrice) && $additionalAmountPrice > 0)
									<!-- Products Subtotal (without additional amount) -->
									<tr>
										<td style="text-align: left; padding-top: 4px; padding-bottom: 4px; font-family: 'Inter', sans-serif;">Products Subtotal</td>
										<td style="text-align: right; padding-top: 4px; padding-bottom: 4px;">{{ $currency }} {{ number_format($subTotal - $additionalAmountPrice, 2, '.', ',') }}</td>
									</tr>
									<tr>
										<td style="text-align: left; padding-top: 4px; padding-bottom: 4px; font-family: 'Inter', sans-serif;">{{ $additionalAmountName }}</td>
										<td style="text-align: right; padding-top: 4px; padding-bottom: 4px;">{{ $currency }} {{ number_format($additionalAmountPrice, 2, '.', ',') }}</td>
									</tr>
									@endif


									<tr>
										<td style="text-align: left; padding-top: 4px; padding-bottom: 4px; font-family: 'Inter', sans-serif; {{ isset($additionalAmountPrice) && $additionalAmountPrice > 0 ? 'font-weight: 600;' : '' }}">Invoice Subtotal</td>
										<td style="text-align: right; padding-top: 4px; padding-bottom: 4px; font-family: 'Inter', sans-serif; {{ isset($additionalAmountPrice) && $additionalAmountPrice > 0 ? 'font-weight: 600;' : '' }}">{{ $currency }} {{ number_format($subTotal, 2, '.', ',') }}</td>
									</tr>


									@if ($discount > 0)
									<tr>
										<td style="color: #15803d; text-align: left; padding-top: 4px; padding-bottom: 4px; font-family: 'Inter', sans-serif;">Coupon Discount</td>
										<td style="color: #15803d; text-align: right; padding-top: 4px; padding-bottom: 4px;">- {{ $currency }} {{ number_format($discount, 2, '.', ',') }}</td>
									</tr>
									<tr>
										<td style="text-align: left; padding-top: 4px; padding-bottom: 4px; font-family: 'Inter', sans-serif;">Subtotal After Coupon Discount</td>
										<td style="text-align: right; padding-top: 4px; padding-bottom: 4px;">{{ $currency }} {{ number_format($subTotal - $discount, 2, '.', ',') }}</td>
									</tr>
									@endif


									@if ($additionalDiscountAmount > 0)
									<tr>
										<td style="color: #15803d; text-align: left; padding-top: 4px; padding-bottom: 4px; font-family: 'Inter', sans-serif;">{{ $additionalDiscountReason }} @if($additionalDiscountPercentage) ({{ $additionalDiscountPercentage }}%) @endif</td>
										<td style="color: #15803d; text-align: right; padding-top: 4px; padding-bottom: 4px;">- {{ $currency }} {{ number_format($additionalDiscountAmount, 2, '.', ',') }}</td>
									</tr>
									<tr>
										<td style="text-align: left; padding-top: 4px; padding-bottom: 4px; font-family: 'Inter', sans-serif;">Subtotal After {{ $additionalDiscountReason }}</td>
										<td style="text-align: right; padding-top: 4px; padding-bottom: 4px;">{{ $currency }} {{ number_format($subTotal - $discount - $additionalDiscountAmount, 2, '.', ',') }}</td>
									</tr>
									@endif


									@if ($liftGateCharge > 0)
									<tr>
										<td style="text-align: left; padding-top: 4px; padding-bottom: 4px; font-family: 'Inter', sans-serif;">Lift Gate Fee</td>
										<td style="text-align: right; padding-top: 4px; padding-bottom: 4px;">{{ $currency }} {{ number_format($liftGateCharge, 2, '.', ',') }}</td>
									</tr>
									@endif

									@if ($residentialAddressCharge > 0)
									<tr>
										<td style="text-align: left; padding-top: 4px; padding-bottom: 4px; font-family: 'Inter', sans-serif;">Residential Delivery Fee</td>
										<td style="text-align: right; padding-top: 4px; padding-bottom: 4px;">{{ $currency }} {{ number_format($residentialAddressCharge, 2, '.', ',') }}</td>
									</tr>
									@endif

									@if ($insideDeliveryCharge > 0)
									<tr>
										<td style="text-align: left; padding-top: 4px; padding-bottom: 4px; font-family: 'Inter', sans-serif;">Inside Delivery Fee</td>
										<td style="text-align: right; padding-top: 4px; padding-bottom: 4px;">{{ $currency }} {{ number_format($insideDeliveryCharge, 2, '.', ',') }}</td>
									</tr>
									@endif

									@if (in_array(config('app.website'), ['US', 'US_T']))
									<tr>
										<td style="text-align: left; padding-top: 4px; padding-bottom: 4px; font-family: 'Inter', sans-serif;">Shipping Charge</td>
										<td style="text-align: right; padding-top: 4px; padding-bottom: 4px;">
											{!! $shippingCharge > 0 ? $currency . ' ' . number_format($shippingCharge, 2, '.', ',') : "<span style='color: green; font-family: \"Inter\", sans-serif;'>Free</span>" !!}
										</td>
									</tr>
									@endif

									<tr>
										<td style="text-align: left; padding-top: 4px; padding-bottom: 4px; font-family: 'Inter', sans-serif; font-weight: 600;">Amount Before Tax</td>
										<td style="text-align: right; padding-top: 4px; padding-bottom: 4px;">{{ $currency }} {{ number_format($amountBeforeTax, 2, '.', ',') }}</td>
									</tr>

									<tr>
										<td style="text-align: left; padding-top: 4px; padding-bottom: 4px; font-family: 'Inter', sans-serif;">{{ $taxName }} ({{ $taxPercent }}%)</td>
										<td style="text-align: right; padding-top: 4px; padding-bottom: 4px;">{{ $currency }} {{ number_format($taxAmount, 2, '.', ',') }}</td>
									</tr>

									@if (!in_array(config('app.website'), ['US', 'US_T']))
									<tr>
										<td style="text-align: left; padding-top: 4px; padding-bottom: 4px; font-family: 'Inter', sans-serif;">Shipping Charge</td>
										<td style="text-align: right; padding-top: 4px; padding-bottom: 4px;">
											{!! $shippingCharge > 0 ? $currency . ' ' . number_format($shippingCharge, 2, '.', ',') : "<span style='color: green; font-family: \"Inter\", sans-serif;'>Free</span>" !!}
										</td>
									</tr>
									@endif
								</tbody>
							</table>
						</div>

						<!-- Net Total Row -->
						<table width="100%" style="color: #FF0000; background-color: #E7E7E7; padding: 8px; font-weight: 600; border-collapse: collapse; margin: 0;">
							<tr>
								<td style="text-align: left; font-family: 'Inter', sans-serif;">Net Total Inclusive {{ $taxName }}</td>
								<td style="text-align: right;">{{ $currency }} {{ number_format($total, 2, '.', ',') }}</td>
							</tr>
						</table>

						<!-- Total in Words Row -->
						<table width="100%" style="border-collapse: collapse; margin: 0; font-weight: 600; font-family: 'Inter', sans-serif;">
							<tr>
								<td style="padding: 5px 8px; text-align: left;">
									{{ $totalInWords }}
								</td>
							</tr>
						</table>

						<!-- Pay Now Button Row -->
						<table width="100%" style="border-collapse: collapse; margin: 0; font-family: 'Inter', sans-serif;">
							<tr>
								<td style="text-align: right; padding: 8px 8px 8px 16px;">
									<a href="{{ $payNowUrl }}" target="_blank" rel="noopener noreferrer" style="background-color: #26683a; color: #ffffff; padding: 10px 20px; text-decoration: none; font-size: 14px; border-radius: 5px; display: inline-block; font-family: 'Noto Sans', sans-serif;">
										Pay Now
									</a>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>

			@if(($index === 0 && in_array(count($chunk), [1, 2])) || ($index > 0 && $index === count($chunks)-1 && in_array(count($chunk), [1, 2, 3])))
			<table style="width: 100%; border-top: 1px solid black; margin-top: 10px; padding-top: 2px; font-size: 12px; font-family: 'Inter', sans-serif; position: absolute; bottom: 70px;">
				<tr>
					<td style="text-align: left;">
						Order Online for Fast Shipping & Lower Prices at
						<a href="{{ $siteURL }}" target="_blank" rel="noopener noreferrer" style="color: #186737; font-family: 'Inter', sans-serif;">{{ $siteURL }}</a>
					</td>
					<td style="text-align: right; font-family: 'Inter', sans-serif;">
						Page {{ $pageNumber++ }}
					</td>
				</tr>
			</table>

			<div style="page-break-before: always;"></div>

			{{-- Header --}}
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
						<p style="margin: 0;">{{ $companyStreet }}</p>
						<p style="margin: 0;">{{ $companyCity }}</p>
						<p style="margin: 0;">Phone: {{ $companyPhone }}</p>
						<p style="margin: 0;">Email: {{ $siteEmail }}</p>
						<p style="margin: 0;">{{ $siteURL }}</p>
					</td>
				</tr>
			</table>
			@endif

			{{-- Bank Details --}}
			<table style="width: 100%; border-spacing: 0; margin-top: 0px; font-family: 'Inter', sans-serif;">
				<tr>
					<!-- Bank Details Table Cell -->
					<td style="width: 42%; vertical-align: top; padding: 15px 0px; font-family: 'Inter', sans-serif;">
						<table style="width: 100%; border-collapse: collapse; font-size: 12px; border: 1px solid black; font-family: 'Inter', sans-serif;">
							<tr>
								<td colspan="2" style="background-color: #d9d9d9; text-align: center; padding: 8px; font-weight: 600; font-size: 15px; font-family: 'Inter', sans-serif;">
									Payment via bank transfer
								</td>
							</tr>
							<tr>
								<td style="background-color: #E7E7E7; border-top: 1px solid black; padding: 8px; font-weight: 600; font-family: 'Inter', sans-serif; width: 40%;">
									Account Name
								</td>
								<td style="border-top: 1px solid black; padding: 8px; font-weight: 600; font-family: 'Inter', sans-serif;">
									{{ $companyName }}
								</td>
							</tr>
							@if($siteName == 'US')
							<tr>
								<td style="background-color: #E7E7E7; border-top: 1px solid black; padding: 8px; font-weight: 600; font-family: 'Inter', sans-serif; width: 40%;">
									Beneficiary Address
								</td>
								<td style="border-top: 1px solid black; padding: 8px; font-weight: 600; font-family: 'Inter', sans-serif;">
									{{ $beneficiaryAddress }}
								</td>
							</tr>
							@endif
							<tr>
								<td style="background-color: #E7E7E7; border-top: 1px solid black; padding: 8px; font-weight: 600; font-family: 'Inter', sans-serif; width: 40%;">
									Account No
								</td>
								<td style="border-top: 1px solid black; padding: 8px; font-weight: 600; font-family: 'Inter', sans-serif;">
									{{ $accountNo }}
								</td>
							</tr>
							<tr>
								<td style="background-color: #E7E7E7; border-top: 1px solid black; padding: 8px; font-weight: 600; font-family: 'Inter', sans-serif; width: 40%;">
									Bank
								</td>
								<td style="border-top: 1px solid black; padding: 8px; font-weight: 600; font-family: 'Inter', sans-serif;">
									{{ $bankName }}
								</td>
							</tr>
							@if($siteName == 'US')
							<tr>
								<td style="background-color: #E7E7E7; border-top: 1px solid black; padding: 8px; font-weight: 600; font-family: 'Inter', sans-serif; width: 40%;">
									Routing Code
								</td>
								<td style="border-top: 1px solid black; padding: 8px; font-weight: 600; font-family: 'Inter', sans-serif;">
									{{ $routingCode }}
								</td>
							</tr>
							@else
							<tr>
								<td style="background-color: #E7E7E7; border-top: 1px solid black; padding: 8px; font-weight: 600; font-family: 'Inter', sans-serif; width: 40%;">
									IBAN Number
								</td>
								<td style="border-top: 1px solid black; padding: 8px; font-weight: 600; font-family: 'Inter', sans-serif;">
									{{ $ibanNumber }}
								</td>
							</tr>
							<tr>
								<td style="background-color: #E7E7E7; border-top: 1px solid black; padding: 8px; font-weight: 600; font-family: 'Inter', sans-serif; width: 40%;">
									Swift Code
								</td>
								<td style="border-top: 1px solid black; padding: 8px; font-weight: 600; font-family: 'Inter', sans-serif;">
									{{ $swiftCode }}
								</td>
							</tr>
							@endif
							<tr>
								<td style="background-color: #E7E7E7; border-top: 1px solid black; padding: 8px; font-weight: 600; font-family: 'Inter', sans-serif; width: 40%;">
									In Case Of Cheque Payment
								</td>
								<td style="border-top: 1px solid black; padding: 8px; font-weight: 600; font-family: 'Inter', sans-serif;">
									Please prepare all cheques in favor of <br>
									<span style="color: #FF0000;">{{ $companyName }}</span>
								</td>
							</tr>
						</table>
					</td>

					<!-- Payment Terms Table Cell -->
					<td style="width: 58%; vertical-align: top; padding: 15px 0px 15px 15px; font-family: 'Inter', sans-serif;">
						<table style="width: 100%; border: 1px solid black; background-color: white; font-size: 12px; border-collapse: collapse; font-family: 'Inter', sans-serif; height: 250px;">
							<thead>
								<tr style="background-color: #d9d9d9; font-family: 'Inter', sans-serif;">
									<th colspan="2" style="text-align: center; padding: 8px; font-weight: 600; font-size: 15px; font-family: 'Inter', sans-serif;">
										Customer Payment Terms & Order Processing Timeline
									</th>
								</tr>
							</thead>
							<tbody>
								<tr style="vertical-align: top;">
									<td colspan="2" style="padding: 8px; font-size: 12px; line-height: 1.6; border-top: 1px solid black; font-family: 'Inter', sans-serif;">
										<strong>Bank Transfer (Wire/Local):</strong> Orders are processed after 2–3 business days upon receipt of funds.<br>
										@if($siteName == 'US')
										<strong>ACH Payments:</strong> Orders are processed after 3 business days from payment receipt.<br>
										@endif
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

			<p style="font-weight: 600; text-align: center; margin: 0 auto; margin-bottom: 10px; font-family: 'Inter', sans-serif; position: absolute; bottom: 50px; width: 100%;">
				This is a system generated Invoice. Hence, no stamp or signature required.
			</p>

			<table style="width: 100%; border-top: 1px solid black; margin-top: 10px; padding-top: 2px; font-size: 12px; font-family: 'Inter', sans-serif; position: absolute; bottom: 60px;">
				<tr>
					<td style="text-align: left;">
						Order Online for Fast Shipping & Lower Prices at
						<a href="{{ $siteURL }}" target="_blank" rel="noopener noreferrer" style="color: #186737; font-family: 'Inter', sans-serif;">{{ $siteURL }}</a>
					</td>
					<td style="text-align: right; font-family: 'Inter', sans-serif;">
						Page {{ $pageNumber }}
					</td>
				</tr>
			</table>
		</div>
	</div>
</body>
</html>