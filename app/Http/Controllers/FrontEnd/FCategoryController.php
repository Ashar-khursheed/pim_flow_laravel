<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

use App\Models\Category;
use App\Models\Product;
use App\Models\SeoManagement;

class FCategoryController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/frontend-categories",
	 *     tags={"Frontend-Category"},
	 *     summary="Get product categories with hierarchical structure",
	 *     description="Retrieves published product categories in a parent-child hierarchical structure. Supports filtering by parent category (via ID or slug), limiting child categories per parent, and optionally including the parent category itself in the response. Categories are returned with translations, SEO URLs, and product counts.",
	 *     @OA\Parameter(name="parent_id", in="query", description="Filter categories by parent ID.", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="limit", in="query", description="Maximum number of child categories to load per parent category.", example=5, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="slug", in="query", description="Filter by parent category slug instead of ID.", @OA\Schema(type="string", example="kitchen-equipment")),
	 *     @OA\Parameter(name="with_parent", in="query", description="Whether to include the parent category details in the response along with its children", @OA\Schema(type="boolean", example=true)),
	 *     @OA\Response(response=200, description="Categories retrieved successfully.", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function index(Request $request)
	{
		$parentID = $request->get('parent_id');
		$limit = $request->get('limit');

		$records = Category::select([
			'id', 'name', 'slug', 'parent_id',
			'image', 'order', 'last_child'
		])
		->with([
			'translations',
			'seoUrl:id,relational_id,relational_type,url',
			'publishedChildren' => function($query) use ($limit) {
				if ($limit) {
					$query->take($limit);
				}
			}
		])
		->withCount('products')
		->where('status', 'published');

		if ($parentID) {

			$records->where(function ($query) use ($filterId) {
				if with_parent true $query->where('id', $filterId)
				for all condition->orWhere('parent_id', $filterId);
			});
		} else {
			$records->where('parent_id', 0);
		}

		$records = $records->orderBy('order');
		$records = $records->get();

		// $cacheKey = $parentID ? "categories_index_$parentID" : "categories_index_all";

		// $categoriesMenus = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($records) {
		// 	return $records->get();
		// });

		return response()->json($records)->header('Cache-Control', 'public, max-age=86400');
	}
}
