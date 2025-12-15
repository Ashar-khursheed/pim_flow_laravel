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
	 *     @OA\Parameter(name="parent_id", in="query", description="Filter categories by parent ID.", @OA\Schema(type="integer", example=1)),
	 *     @OA\Parameter(name="limit", in="query", description="Maximum number of child categories to load per parent category.", @OA\Schema(type="integer", example=5)),
	 *     @OA\Parameter(name="slug", in="query", description="Filter by parent category slug instead of ID.", @OA\Schema(type="string", example="kitchen-equipment")),
	 *     @OA\Parameter(name="with_parent", in="query", description="Whether to include the parent category details in the response along with its children", @OA\Schema(type="boolean", example=true)),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "order"})),
	 *     @OA\Response(response=200, description="Categories retrieved successfully.", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function index(Request $request)
	{
		$parentID = $request->get('parent_id');
		$limit = $request->get('limit');
		$withParent = $request->boolean('with_parent');
		$slug = $request->get('slug');
		$sortBy = $request->get('sort_by');

		if ($slug) {
			$seoRecord = SeoManagement::where('url', $slug)->where('relational_type', 'Category')->first(['relational_id']);
			if (!$seoRecord) {
				return response()->json([
					'success' => false,
					'message' => 'Category slug not found'
				]);
			}

			/* Check if category exists and is published in one query */
			$categoryExists = Category::where('id', $seoRecord->relational_id)->where('status', 'published')->exists();
			if (!$categoryExists) {
				return response()->json([
					'success' => false,
					'message' => 'Category not found or not published'
				]);
			}

			$parentID = $seoRecord->relational_id;
		}

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
			$records->where(function ($query) use ($parentID, $withParent) {
				$query->where('parent_id', $parentID);
				if ($withParent) {
					$query->orWhere('id', $parentID);
				}
			});
		} else {
			$records->where('parent_id', 0);
		}

		if ($sortBy) {
			$records = $records->orderBy($sortBy);
		}

		$records = $records->get();

		/* Process each record to load last_children */
		$records->each(function ($record) {
			if (!empty($record->last_child)) {
				/* Convert comma-separated IDs to array */
				$lastChildIds = array_map('intval', explode(',', $record->last_child));

				/* Fetch the last children categories */
				$lastChildren = Category::whereIn('id', $lastChildIds)
				->with(['seoUrl:id,relational_id,relational_type,url'])
				->where('status', 'published')
				->select(['id', 'name', 'slug', 'parent_id', 'image', 'order'])
				->get()
				->map(function ($child) {
					return [
						'id' => $child->id,
						'name' => $child->name,
						'slug' => $child->seoUrl?->url ?? $child->slug ?? null,
						'parent_id' => $child->parent_id,
						'image' => $child->image,
						'order' => $child->order,
					];
				});
				$record->last_children = $lastChildren;
			} else {
				$record->last_children = [];
			}

			/* Remove the raw last_child field if not needed */
			unset($record->last_child);
		});

		return response()->json([
			'success' => true,
			'message' => 'Categories retrieved successfully.',
			'data' => $records
		]);

		// $cacheKey = $parentID ? "categories_index_$parentID" : "categories_index_all";

		// $categoriesMenus = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($records) {
		// 	return $records->get();
		// });

		// return response()->json($records)->header('Cache-Control', 'public, max-age=86400');
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend-categories/with-parents",
	 *     tags={"Frontend-Category"},
	 *     summary="Fetch all categories with parents",
	 *     @OA\Parameter(name="category_id", in="query", description="Filter categories by category ID.", @OA\Schema(type="integer", example=1)),
	 *     @OA\Parameter(name="category_type", in="query", description="Filter by category type.", @OA\Schema(type="string", example="home")),
	 *     @OA\Response(response=200, description="All categories fetched successfully", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function fetchCategoriesWithParents(Request $request)
	{
		$categoryID = $request->get('category_id');
		$categoryType = $request->get('category_type');

		$categories = Category::select(['id', 'name', 'parent_id', 'image'])
		->where('status', 'published')
		->with([
			'translations',
			'seoUrl:id,relational_id,relational_type,url',
			'parentRecursive'
		])
		->withCount('products');

		if ($categoryID ) {
			$categories = $categories->where('id', $categoryID);
		}

		if ($categoryType == 'home') {
			$homeCategories = home_categories();
			$orderField = 'FIELD(name, ' . implode(',', array_fill(0, count($homeCategories), '?')) . ')';
			$categories = $categories->whereDoesntHave('children')
			->whereIn('name', $homeCategories)
			->orderByRaw($orderField, $homeCategories);
		} else {
			$categories = $categories->orderBy('id');
		}

		$categories = $categories->get();

		$categories->transform(function ($category) {
			$parents = [];
			$current = $category->parentRecursive;

			while ($current) {
				$localeNames = [];
				if ($current->translations) {
					foreach ($current->translations as $translation) {
						$localeNames[$translation->locale] = $translation->name_tr;
					}
				}
				$parents[] = [
					'id' => $current->id,
					'name' => $localeNames,
					'slug' => optional($current->seoUrl)->url ?? $current->slug ?? null,
				];
				$current = $current->parentRecursive ?? null;
			}

			if ($category->translations) {
				foreach ($category->translations as $translation) {
					$categoryLocaleNames[$translation->locale] = $translation->name_tr;
				}
			}

			$category->slug = optional($category->seoUrl)->url ?? null;
			$category->name = $categoryLocaleNames;
			$category->parents = array_reverse($parents);

			unset($category->seoUrl);
			unset($category->parentRecursive);
			unset($category->translations);

			return $category;
		});

		return response()->json([
			'success' => true,
			'message' => 'Categories retrieved successfully.',
			'data' => $categories
		]);
	}
}
