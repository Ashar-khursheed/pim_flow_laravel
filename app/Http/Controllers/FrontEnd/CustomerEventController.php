<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\FrontEnd\CustomerEvent;
use Illuminate\Http\Request;

class CustomerEventController extends Controller
{
	/**
	 * @OA\Post(
	 *     path="/api/frontend/customer-events",
	 *     tags={"FrontEnd-Customer Events"},
	 *     summary="Track a customer event",
	 *     description="Store a customer interaction event for analytics",
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             @OA\Property(property="event_type", type="string", example="click"),
	 *             @OA\Property(property="event_time", type="string", format="date-time", example="2025-07-25 14:23:00"),
	 *             @OA\Property(property="page", type="string", example="/product/123"),
	 *             @OA\Property(property="element", type="string", nullable=true, example="#add-to-cart-button"),
	 *             @OA\Property(property="customer_id", type="integer", nullable=true, example=1),
	 *             @OA\Property(property="session_id", type="string", example="abc123xyz"),
	 *             @OA\Property(property="ip_address", type="string", example="192.168.1.100"),
	 *             @OA\Property(property="user_agent", type="string", example="Mozilla/5.0 ..."),
	 *             @OA\Property(
	 *                 property="extra_data",
	 *                 type="object",
	 *                 @OA\Property(property="product_id", type="integer", example=123),
	 *                 @OA\Property(property="product_name", type="string", example="Apple iPhone 14"),
	 *                 @OA\Property(property="referrer", type="string", example="https://google.com"),
	 *                 @OA\Property(property="time_spent_seconds", type="integer", example=45),
	 *                 @OA\Property(property="conversion_value", type="number", format="float", example=599.99),
	 *                 @OA\Property(property="conversion_type", type="string", example="purchase")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Event tracked successfully", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function store(Request $request)
	{
		$data = $request->validate([
			'event_type' => 'required',
			'event_time' => 'required|date',
			'page' => 'required|string|max:255',
			'element' => 'nullable|string|max:255',
			'customer_id' => 'nullable|exists:customers,id',
			'session_id' => 'required|string|max:100',
			'ip_address' => 'required|ip',
			'user_agent' => 'required|string',
			'extra_data' => 'nullable|array',
		]);

		$record = CustomerEvent::create($data);

		return response()->json([
			'success' => true,
			'message' => 'Customer event recorded successfully.',
			'data' => $record
		], 201);
	}

	/**
	 * Display the specified resource.
	 */
	public function show(CustomerEvent $customerEvent)
	{
		//
	}

	/**
	 * Update the specified resource in storage.
	 */
	public function update(Request $request, CustomerEvent $customerEvent)
	{
		//
	}

	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy(CustomerEvent $customerEvent)
	{
		//
	}
}
