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
		// $addresses = CustomerAddress::with(['country:id,name', 'state:id,name', 'city:id,name'])
		$addresses = CustomerAddress::where('customer_id', $customerId)
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
	 *             @OA\Property(property="country", type="string", example=101),
	 *             @OA\Property(property="state", type="string", example=10),
	 *             @OA\Property(property="city", type="string", example=5),
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
			'type' => 'nullable',
			'address' => 'required|string',
			'country' => 'nullable|string|exists:countries,name',
			'state' => 'nullable|string',
			'city' => 'nullable|string',
			'zip_code' => 'nullable|string|max:20',
			'is_default' => 'nullable|boolean',
		]);

		if (!empty($validated['is_default']) && $validated['is_default']) {
			CustomerAddress::where('customer_id', $validated['customer_id'])->update(['is_default' => false]);
		}

		$address = CustomerAddress::create([
			'customer_id' => $validated['customer_id'],
			'type' => $validated['type'],
			'address' => $validated['address'],
			'country' => $validated['country'],
			'state' => $validated['state'],
			'city' => $validated['city'],
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
		$address = CustomerAddress::find($id);

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
	 *             @OA\Property(property="country", type="string", example=102),
	 *             @OA\Property(property="state", type="string", example=20),
	 *             @OA\Property(property="city", type="string", example=15),
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
			'type' => 'nullable',
			'address' => 'nullable|string|max:191',
			'country' => 'nullable|string|exists:countries,name',
			'state' => 'nullable|string',
			'city' => 'nullable|string',
			'zip_code' => 'nullable|string|max:20',
			'is_default' => 'nullable|boolean',
		]);

		if (!empty($validated['is_default']) && $validated['is_default']) {
			CustomerAddress::where('customer_id', $address->customer_id)->where('id', '!=', $address->id)->update(['is_default' => false]);
		}

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
