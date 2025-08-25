<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

use App\Models\SeoManagement;
use App\Models\FrontEnd\Customer;
use App\Models\FrontEnd\CustomerAddress;
use App\Models\FrontEnd\PrePurchaseClaim;

use App\Jobs\Welcome\PreClaimWelcomeMailJob;
use App\Jobs\Welcome\PreClaimMailJob;

class PrePurchaseClaimController extends BaseController
{
	/**
	 * @OA\Post(
	 *     path="/api/frontend/pre-purchase-claims",
	 *     summary="Create a new pre-purchase claim",
	 *     tags={"FrontEnd-PrePurchaseClaims"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"customer_name", "customer_email", "product_url", "product_price", "product_quantity", "competitor_product_url", "competitor_product_price", "customer_address", "customer_country", "customer_city", "is_confirm", "is_agree"},
	 *
	 *                 @OA\Property(property="customer_name", type="string", example="John Doe"),
	 *                 @OA\Property(property="customer_business_name", type="string", example="Doe Enterprises"),
	 *                 @OA\Property(property="customer_email", type="string", format="email", example="john@example.com"),
	 *                 @OA\Property(property="customer_country_code", type="string", example="+91"),
	 *                 @OA\Property(property="customer_mobile_number", type="string", example="971500000000"),
	 *                 @OA\Property(property="customer_address", type="string", example="123 Main Street"),
	 *                 @OA\Property(property="customer_country", type="string", example="UAE"),
	 *                 @OA\Property(property="customer_state", type="string", example="Dubai"),
	 *                 @OA\Property(property="customer_city", type="string", example="Dubai"),
	 *                 @OA\Property(property="customer_zipcode", type="string", example="560001"),
	 *                 @OA\Property(property="product_url", type="string", example="https://www.thehorecastore.com/products/abc"),
	 *                 @OA\Property(property="product_price", type="number", format="float", example=200.00),
	 *                 @OA\Property(property="product_quantity", type="integer", example=2),
	 *                 @OA\Property(property="competitor_product_url", type="string", example="https://competitor.com/product/xyz"),
	 *                 @OA\Property(property="competitor_product_price", type="number", format="float", example=180.00),
	 *                 @OA\Property(property="competitor_product_shipping_charge", type="number", format="float", example=50.00),
	 *                 @OA\Property(property="competitor_screenshot", type="string", format="binary", description="Competitor screenshot image (jpeg, png, jpg, webp, max:2MB)"),
	 *                 @OA\Property(property="is_confirm", type="boolean", example=true),
	 *                 @OA\Property(property="is_agree", type="boolean", example=true),
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Created successfully", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function store(Request $request)
	{
		/* Validate request */
		$request->validate([
			'customer_name' => 'required|string|max:255',
			'customer_business_name' => 'nullable|string|max:255',
			'customer_email' => 'required|email|max:255',
			'customer_country_code' => 'nullable|string|max:20',
			'customer_mobile_number' => 'nullable|string|max:20',

			'product_url' => [
				'required',
				'url',
				function ($attribute, $value, $fail) {
					$frontEndUrl = rtrim(config('app.url'), '/');
					if (strpos($value, $frontEndUrl) !== 0) {
						$fail("The {$attribute} must start with {$frontEndUrl}");
					}
				},
			],
			'product_quantity' => 'required|integer|min:1',
			'product_price' => 'required|numeric|min:0',
			'competitor_product_url' => 'required|url',
			'competitor_product_price' => 'required|numeric|min:0',
			'competitor_product_shipping_charge' => 'nullable|numeric|min:0',
			'competitor_screenshot' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:2048',

			'customer_address' => 'required|string',
			'customer_country' => 'required|string',
			'customer_state' => 'nullable|string',
			'customer_city' => 'required|string',
			'customer_zipcode' => 'nullable|string|max:20',

			'is_confirm' => 'required|in:true,1',
			'is_agree' => 'required|in:true,1',
		]);

		/* Extract slug part of product_url */
		$frontEndUrl = config('app.url');
		$productSlug = str_replace($frontEndUrl . '/products/', '', $request->product_url);

		/* Find product ID from SeoManagement */
		$productID = SeoManagement::where('url', $productSlug)->value('relational_id');
		if (!$productID) {
			return response()->json([
				'success' => false,
				'message' => 'Invalid product URL provided.'
			], 422);
		}

		$batch = Bus::batch([])->name('Pre Claim Mails')->dispatch();

		$customer = Customer::where('email', $request->customer_email)->first();
		if (!$customer) {
			$randomPassword = Str::random(8);

			$customer = Customer::create([
				'name' => $request->customer_name,
				'business_name' => $request->customer_business_name,
				'email' => $request->customer_email,
				'password' => Hash::make($randomPassword),
				'type' => 'Private',
				'country_code' => $request->customer_country_code,
				'mobile_number' => $request->customer_mobile_number,
			]);

			$customerAddress = CustomerAddress::create([
				'customer_id' => $customer->id,
				'type' => 'Home',
				'address' => $request->customer_address,
				'country' => $request->customer_country,
				'state' => $request->customer_state,
				'city' => $request->customer_city,
				'zip_code' => $request->customer_zipcode,
				'is_default' => true,
			]);

			$batch->options['queue'] = config('app.website') . '_CLM_WLCM';
			$batch->add(new PreClaimWelcomeMailJob([
				'recordId' => $customer->id,
				'randomPassword' => $randomPassword,
			]));
		} else {
			$customerAddress = $customer->customerAddress()
			->where('address', $request->customer_address)
			->where('country', $request->customer_country)
			->where('city', $request->customer_city)
			->first();

			/* If not found, create new */
			if (!$customerAddress) {
				$customerAddress = CustomerAddress::create([
					'customer_id' => $customer->id,
					'type' => 'Home',
					'address' => $request->customer_address,
					'country' => $request->customer_country,
					'state' => $request->customer_state,
					'city' => $request->customer_city,
					'zip_code' => $request->customer_zipcode,
				]);
			}
		}

		/* Upload competitor screenshot if provided */
		$competitorProductImgURL = null;
		if ($request->hasFile('competitor_screenshot')) {
			$competitorProductImgURL = uploadImageToWebpS3FromFile(
				$request,
				'competitor_screenshot',
				env('STORAGE_ENV') . '/pre_purchase_claims/screenshot'
			);
		}

		$claim = PrePurchaseClaim::create([
			'customer_id' => $customer->id,
			'customer_address_id' => $customerAddress->id ?? null,
			'product_id' => $productID,
			'product_price' => $request->product_price,
			'product_quantity' => $request->product_quantity,
			'competitor_product_url' => $request->competitor_product_url,
			'competitor_product_price' => $request->competitor_product_price,
			'competitor_product_shipping_charge' => $request->competitor_product_shipping_charge ?? 0,
			'competitor_screenshot_url' => $competitorProductImgURL,
		]);

		$batch->options['queue'] = config('app.website') . '_PRE_CLM';
		$batch->add(new PreClaimMailJob([
			'recordId' => $claim->id,
		]));

		$this->sendToOdoo($guestCustomer);

		return response()->json([
			'success' => true,
			'message' => __("msg_create"),
			'data' => $claim
		], 201);
	}

	private function sendToOdoo($customer)
	{
		try {
			$response = Http::withHeaders([
				'Content-Type' => 'application/json',
				'Cookie' => 'session_id=57ddd7bc96ea7900b2615ef1db5e65409c0e0e98',
			])->post('https://horecastore-staging-20736821.dev.odoo.com/web/dataset/call_kw', [
				'jsonrpc' => '2.0',
				'method' => 'call',
				'params' => [
					'model' => 'res.partner',
					'method' => 'create',
					'args' => [[
						'company_type' => 'person',
						'name' => $customer->name,
						'email' => $customer->email,
						'phone' => ($customer->country_code ? $customer->country_code . ' ' : '') . $customer->mobile_number,
						'mobile' => ($customer->country_code ? $customer->country_code . ' ' : '') . $customer->mobile_number,
						'business_name' =>  $customer->business_name,
						'image_1920' => '',
						'website' => '',
						'is_customer' => "1",
						'vat_not_register' => "0",
						'vat' => '215444',
						'street' => '12',
						'street2' => 'new',
						'city' => 'Surat',
						'zip' => '395006',
						'website_partner_id' => $customer->id,
						'website_customer' => 1
					]],
					'kwargs' => new \stdClass()
				],
				'id' => 1
			]);

			if ($response->successful()) {
				\Log::info('Customer synced to Odoo successfully.', ['odoo_response' => $response->json()]);
			} else {
				\Log::error('Failed to sync customer to Odoo.', [
					'status' => $response->status(),
					'body' => $response->body()
				]);
			}
		} catch (\Exception $e) {
			\Log::error('Exception syncing to Odoo: ' . $e->getMessage());
		}
	}
}
