<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends BaseController
{
	/**
	 * Display a listing of the resource.
	 */
	/**
	 * @OA\Get(
	 *     path="/api/categories",
	 *     summary="Get Category List",
	 *     description="Fetches a list of categories. If 'type' is set to 'Parent', only parent categories (parent_id = 0) will be returned. If 'parent_id' is provided, it fetches all child categories of the given parent.",
	 *     tags={"Categories"},
	 *     @OA\Parameter(
	 *         name="type",
	 *         in="query",
	 *         required=false,
	 *         description="Filter categories by type. Options: 'All' (default), 'Parent'",
	 *         @OA\Schema(
	 *             type="string",
	 *             enum={"All", "Parent"},
	 *             default="All"
	 *         )
	 *     ),
	 *     @OA\Parameter(
	 *         name="parent_id",
	 *         in="query",
	 *         required=false,
	 *         description="Fetch all child categories of a given parent_id",
	 *         @OA\Schema(
	 *             type="integer",
	 *             example=1
	 *         )
	 *     ),
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
	 *         response=200,
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
		// dd(auth()->user());
		$categories = Category::query();

		if ($request->has('parent_id') && is_numeric($request->parent_id)) {
			$categories = $categories->where('parent_id', (int) $request->parent_id);
		}

		elseif ($request->type == 'Parent') {
			$categories = $categories->where('parent_id', 0);
		}

		if($request->filled('page') && $request->filled('length')){
			$page = $request->input('page');
			$length = $request->input('length');
			$categories = $categories->offset(($page - 1)*$length)->limit($length);
		}

		$categoriesList = $categories->get();

		return response()->json([
			'message' => 'Category List',
			'categories' => $categoriesList
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
