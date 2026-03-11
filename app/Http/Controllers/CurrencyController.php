<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/currencies",
	 *     summary="Get list of currencies",
	 *     description="Fetches a list of all currencies.",
	 *     tags={"Currencies"},
	 *     @OA\Response(response=200, description="Currencies retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index()
	{
		/* Return all records with minimal fields (for dropdowns) */
		$records = Currency::query()->get(['id', 'title', 'symbol']);

		return response()->json([
			'message' => __("msg_rec_list"),
			'data' => $records
		], 200);
	}

	/**
	 * Show the form for creating a new resource.
	 */
	public function create()
	{
		//
	}

	/**
	 * Store a newly created resource in storage.
	 */
	public function store(Request $request)
	{
		//
	}

	/**
	 * Display the specified resource.
	 */
	public function show(Currency $currency)
	{
		//
	}

	/**
	 * Show the form for editing the specified resource.
	 */
	public function edit(Currency $currency)
	{
		//
	}

	/**
	 * Update the specified resource in storage.
	 */
	public function update(Request $request, Currency $currency)
	{
		//
	}

	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy(Currency $currency)
	{
		//
	}
}
