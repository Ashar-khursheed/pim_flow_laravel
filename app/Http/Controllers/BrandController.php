<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Brand;

class BrandController extends BaseController
{
	/**
	 * Display a listing of the resource.
	 */
	/**
	 * @OA\Get(
	 *     path="/api/brands",
	 *     summary="Get brands List",
	 *     description="Fetches a list of all brands.",
	 *     tags={"Brands"},
	 *     @OA\Parameter(
	 *         name="page",
	 *         in="query",
	 *         description="Page number for pagination. Starts from 1.",
	 *         required=true,
	 *         example=1,
	 *         @OA\Schema(
	 *             type="integer",
	 *             minimum=1
	 *         )
	 *     ),
	 *     @OA\Parameter(
	 *         name="length",
	 *         in="query",
	 *         description="Number of records per page.",
	 *         required=true,
	 *         example=20,
	 *         @OA\Schema(
	 *             type="integer",
	 *             minimum=1
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=201,
	 *         description="Success",
	 *          @OA\MediaType(
	 *              mediaType="application/json",
	 *          )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$brands = Brand::query();

		if($request->filled('page') && $request->filled('length')){
			$page = $request->input('page');
			$length = $request->input('length');
			$brands = $brands->offset(($page - 1)*$length)->limit($length);
		}

		$brands = $brands->pluck('name', 'id');

		return response()->json([
			'success' => true,
			'message' => 'Brand List',
			'brands' => $brands
		]);
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
	public function show(string $id)
	{
		//
	}

	/**
	 * Show the form for editing the specified resource.
	 */
	public function edit(string $id)
	{
		//
	}

	/**
	 * Update the specified resource in storage.
	 */
	public function update(Request $request, string $id)
	{
		//
	}

	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy(string $id)
	{
		//
	}
}
