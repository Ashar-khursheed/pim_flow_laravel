<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;
use App\Models\FrontEnd\CustomerAddress;
use Illuminate\Http\Request;

class CustomerAddressController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/frontend/customer-address",
	 *     summary="Get all addresses of the authenticated customer",
	 *     tags={"Frontend-Customer-Address"},
	 *     @OA\Response(response=200, description="List retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index()
	{
		$customerId = Auth::id();
		if (!$customerId) {
			return response()->json([
				'success' => false,
				'message' => 'User not authenticated.'
			]);
		}

		$addresses = CustomerAddress::with(['country:id,name', 'state:id,name', 'city:id,name'])
		->where('customer_id', $customerId)
		->get();

		if ($addresses->isEmpty()) {
			return response()->json([
				'success' => false,
				'message' => 'No addresses found for this customer.'
			], 404);
		}

		return response()->json([
			'success' => true,
			'data' => $addresses
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/customer-address",
	 *     summary="Create a new customer address",
	 *     tags={"Frontend-Customer-Address"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"type", "address"},
	 *             @OA\Property(property="type", type="string", example="home"),
	 *             @OA\Property(property="address", type="string", example="123 Main Street"),
	 *             @OA\Property(property="country_id", type="integer", example=101),
	 *             @OA\Property(property="state_id", type="integer", example=10),
	 *             @OA\Property(property="city_id", type="integer", example=5),
	 *             @OA\Property(property="zip_code", type="string", example="123456"),
	 *             @OA\Property(property="is_default", type="boolean", example=true),
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Created successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request)
	{
		$validated = $request->validate([
			'type' => 'required|in:home,work,other',
			'address' => 'required|string',
			'country_id' => 'nullable|integer',
			'state_id' => 'nullable|integer',
			'city_id' => 'nullable|integer',
			'zip_code' => 'nullable|string|max:20',
			'is_default' => 'nullable|boolean',
		]);

		$address = CustomerAddress::create([
			'customer_id' => auth()->id(),
			'type' => $validated['type'],
			'address' => $validated['address'],
			'country_id' => $validated['country_id'],
			'state_id' => $validated['state_id'],
			'city_id' => $validated['city_id'],
			'zip_code' => $validated['zip_code'] ?? null,
			'is_default' => $validated['is_default'] ?? false,
			'created_by' => auth()->id(),
		]);

		return response()->json([
			'success' => true,
			'message' => 'Address created successfully.',
			'data' => $address
		], 201);
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/customer-address/{id}",
	 *     summary="Get customer address details",
	 *     description="Fetches customer address details based on the given ID.",
	 *     tags={"Frontend-Customer-Address"},
	 *     @OA\Parameter(name="id", in="path", required=true, description="Customer Address ID", @OA\Schema(type="integer", example=1)),
	 *     @OA\Response(response=200, description="Retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($id)
	{
		$address = CustomerAddress::with(['country:id,name', 'state:id,name', 'city:id,name'])->where('customer_id', auth()->id())->where('id', $id)->first();

		if (!$address) {
			return response()->json([
				'success' => false,
				'message' => 'Customer address not found.'
			], 404);
		}

		return response()->json([
			'success' => true,
			'data' => $address
		]);
	}

	/**
	 * @OA\Put(
	 *     path="/api/frontend/customer-address/{id}",
	 *     summary="Update a specific customer address",
	 *     tags={"Frontend-Customer-Address"},
	 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"type", "address"},
	 *             @OA\Property(property="type", type="string", example="home"),
	 *             @OA\Property(property="address", type="string", example="456 New Street"),
	 *             @OA\Property(property="country_id", type="integer", example=102),
	 *             @OA\Property(property="state_id", type="integer", example=20),
	 *             @OA\Property(property="city_id", type="integer", example=15),
	 *             @OA\Property(property="zip_code", type="string", example="123123"),
	 *             @OA\Property(property="is_default", type="boolean", example=false)
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Updated successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function update(Request $request, $id)
	{
		$address = CustomerAddress::where('customer_id', auth()->id())->where('id', $id)->first();

		if (!$address) {
			return response()->json([
				'success' => false,
				'message' => 'Address not found.'
			], 404);
		}

		$validated = $request->validate([
			'type' => 'nullable|in:home,work,other',
			'address' => 'nullable|string|max:191',
			'country_id' => 'nullable|integer',
			'state_id' => 'nullable|integer',
			'city_id' => 'nullable|integer',
			'zip_code' => 'nullable|string|max:20',
			'is_default' => 'nullable|boolean',
		]);

		$address->update($validated);

		return response()->json([
			'success' => true,
			'message' => 'Address updated successfully.',
			'data' => $address
		]);
	}

	/**
	 * @OA\Delete(
	 *     path="/api/frontend/customer-address/{id}",
	 *     summary="Delete a specific customer address",
	 *     tags={"Frontend-Customer-Address"},
	 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
	 *     @OA\Response(response=200, description="Deleted successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function destroy($id)
	{
		$address = CustomerAddress::where('customer_id', auth()->id())->where('id', $id)->first();

		if (!$address) {
			return response()->json([
				'success' => false,
				'message' => 'Address not found.'
			], 404);
		}

		$address->delete();

		return response()->json([
			'success' => true,
			'message' => 'Address deleted successfully.'
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/customer-address/default",
	 *     summary="Set default address",
	 *     tags={"Frontend-Customer-Address"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"address_id"},
	 *             @OA\Property(property="address_id", type="integer", example=1)
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Default address updated successfully"),
	 *     @OA\Response(response=401, description="Unauthorized"),
	 *     @OA\Response(response=500, description="Server error")
	 * )
	 */
	 public function updateDefaultAddress(Request $request)
	 {
	 	Log::info('Entered updateDefaultAddress method.');

	 	$userId = Auth::id();
	 	if (!$userId) {
	 		return response()->json(['message' => 'User not authenticated.'], 401);
	 	}

	 	$validatedData = $request->validate([
	 		'address_id' => 'required|integer|exists:ec_customer_addresses,id'
	 	]);

	 	try {
			// Remove current default
	 		Address::where('customer_id', $userId)->where('is_default', 1)->update(['is_default' => 0]);

			// Set the requested address as default
	 		$updated = Address::where('id', $validatedData['address_id'])->update(['is_default' => 1]);

	 		if ($updated) {
	 			Log::info('Default address updated successfully.');
	 			return response()->json([
	 				'message' => 'Default address updated successfully.',
	 				'success' => true,
	 			]);
	 		}

	 		Log::error('Failed to update the default address.');
	 		return response()->json([
	 			'error' => 'Failed to set default address.',
	 			'success' => false,
	 		], 500);
	 	} catch (\Exception $e) {
	 		Log::error('Unexpected error in updateDefaultAddress: ', ['error' => $e->getMessage()]);
	 		return response()->json([
	 			'error' => 'An unexpected error occurred.',
	 			'details' => $e->getMessage()
	 		], 500);
	 	}
	 }
}
