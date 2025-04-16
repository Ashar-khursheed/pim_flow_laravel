<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log; // ✅ Add this line
use Illuminate\Support\Facades\Cache;

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
	 *             enum={"All", "Super Parent", "Leaf Child"},
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



		if ($request->type == 'Super Parent') {
			$categories = $categories->where('parent_id', 0);
		} elseif ($request->type == 'Leaf Child') {
			$categories = $categories->whereDoesntHave('children');
		} elseif ($request->has('parent_id') && is_numeric($request->parent_id)) {
			$categories = $categories->where('parent_id', (int) $request->parent_id);
		}

		if($request->filled('page') && $request->filled('length')){
			$page = $request->input('page');
			$length = $request->input('length');
			$categories = $categories->offset(($page - 1)*$length)->limit($length);
		}

		$categoriesList = $categories->get(['id', 'name', 'parent_id', 'image', 'slug', 'is_featured']);

				// Append full image URL
		$categoriesList->transform(function ($category) {
			$category->image = $category->image
				? asset('storage/' . $category->image)
				: null;
			return $category;
		});
		return response()->json([
			'success' => true,
			'message' => 'Category List',
			'categories' => $categoriesList
		]);
	}


	/**
 * @OA\Get(
 *     path="/api/allcategories",
 *     summary="Get All Categories",
 *     description="Fetches a hierarchical list of categories. Each category includes its child categories recursively.",
 *     tags={"Categories"},
 *     @OA\Response(
 *         response=200,
 *         description="Successful operation",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="id", type="integer", example=1),
 *                 @OA\Property(property="name", type="string", example="Electronics"),
 *                 @OA\Property(property="slug", type="string", example="electronics"),
 *                 @OA\Property(
 *                     property="children_recursive",
 *                     type="array",
 *                     @OA\Items(
 *                         type="object",
 *                         @OA\Property(property="id", type="integer", example=2),
 *                         @OA\Property(property="name", type="string", example="Mobile Phones"),
 *                         @OA\Property(property="slug", type="string", example="mobile-phones"),
 *                         @OA\Property(
 *                             property="children_recursive",
 *                             type="array",
 *                             @OA\Items(
 *                                 type="object",
 *                                 @OA\Property(property="id", type="integer", example=3),
 *                                 @OA\Property(property="name", type="string", example="Smartphones"),
 *                                 @OA\Property(property="slug", type="string", example="smartphones")
 *                             )
 *                         )
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthorized",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Unauthenticated.")
 *         )
 *     ),
 *     security={{"bearerAuth":{}}}
 * )
 */


 public function allcategories(): JsonResponse
 {
	 $categories = Cache::remember('all_categories', 3600, function () {
		 return Category::where('parent_id', 0)
			 ->with(['children.children'])
			 ->get(['id', 'name', 'slug']);
	 });
 
	 return response()->json([
		 'success' => true,
		 'message' => 'All Categories List',
		 'categories' => $categories
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
