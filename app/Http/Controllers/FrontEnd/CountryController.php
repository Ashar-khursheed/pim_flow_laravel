<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\Country;

class CountryController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/frontend/countries",
	 *     summary="Get list of countries",
	 *     description="Fetches a list of all countries.",
	 *     tags={"Frontend-Countries"},
	 *     @OA\Response(response=200, description="Countries retrieved successfully", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function index()
	{
		$records = Country::get(['id', 'name', 'phone_code', 'icon']);

		return response()->json([
			'message' => __("msg_rec_list"),
			'data' => $records
		], 200);
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/countries/{id}",
	 *     summary="Get a specific country",
	 *     tags={"Countries"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Country ID",
	 *         required=true,
	 *         @OA\Schema(type="string", example="1")
	 *     ),
	 *     @OA\Response(response=200, description="Country retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function show($id)
	{
		$query = Country::with(['currency:id,title,symbol', 'creator:id,first_name,last_name', 'updater:id,first_name,last_name']);
		/* Fetch by ID or Name */
		if (is_numeric($id)) {
			$country = $query->find($id);
		} else {
			$country = $query->where('name', $id)->first();
		}

		if (!$country) {
			return response()->json([
				'success' => false,
				'message' => 'Country not found'
			], 404);
		}

		$data = [
			'id' => $country->id,
			'name' => $country->name,
			'phone_code' => $country->phone_code,
			'icon' => $country->icon,
			'currency_id' => $country->currency_id,
			'currency_title' => $country->currency->title ?? null,
			'currency_symbol' => $country->currency->symbol ?? null,
			'margin' => (float) $country->margin,
			'created_by_name' => $country->creator->name ?? null,
			'updated_by_name' => $country->updater->name ?? null,
			'created_at' => $country->created_at?->format('Y-m-d H:i:s'),
			'updated_at' => $country->updated_at?->format('Y-m-d H:i:s'),
		];

		return response()->json([
			'success' => true,
			'message' => 'Country retrieved successfully',
			'data' => $data
		], 200);
	}
}
