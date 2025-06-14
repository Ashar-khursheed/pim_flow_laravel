<?php

namespace App\Http\Controllers;

use App\Models\FrontEnd\CustomerAddress;
use Illuminate\Http\Request;

class CustomerAddressController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/customers/{customer_id}/addresses",
	 *     summary="Get all addresses of a specific customer",
	 *     tags={"Customer-Address"},
	 *     @OA\Parameter(
	 *         name="customer_id",
	 *         in="path",
	 *         required=true,
	 *         description="ID of the customer",
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function indexByCustomer($customerId)
	{
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
	 *     path="/api/customer-address",
	 *     summary="Create a new customer address",
	 *     tags={"Customer-Address"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"customer_id", "type", "address"},
	 *             @OA\Property(property="customer_id", type="integer", example=1),
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
			'customer_id' => 'required|exists:customers,id',
			'type' => 'required|in:home,work,other',
			'address' => 'required|string',
			'country_id' => 'nullable|integer',
			'state_id' => 'nullable|integer',
			'city_id' => 'nullable|integer',
			'zip_code' => 'nullable|string|max:20',
			'is_default' => 'nullable|boolean',
		]);

		$address = CustomerAddress::create([
			'customer_id' => $validated['customer_id'],
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
	 *     path="/api/customer-address/{id}",
	 *     summary="Get customer address details",
	 *     description="Fetches customer address details based on the given ID.",
	 *     tags={"Customer-Address"},
	 *     @OA\Parameter(name="id", in="path", required=true, description="Customer Address ID", @OA\Schema(type="integer", example=1)),
	 *     @OA\Response(response=200, description="Retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($id)
	{
		$address = CustomerAddress::with(['country:id,name', 'state:id,name', 'city:id,name'])->find($id);

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
	 *     path="/api/customer-address/{id}",
	 *     summary="Update a specific customer address",
	 *     tags={"Customer-Address"},
	 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"customer_id", "type", "address"},
	 *             @OA\Property(property="customer_id", type="integer", example=1),
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
		$address = CustomerAddress::find($id);

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
	 *     path="/api/customer-address/{id}",
	 *     summary="Delete a specific customer address",
	 *     tags={"Customer-Address"},
	 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
	 *     @OA\Response(response=200, description="Deleted successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function destroy($id)
	{
		$address = CustomerAddress::find($id);

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
}
