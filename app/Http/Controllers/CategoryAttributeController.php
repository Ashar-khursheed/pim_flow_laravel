<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\AttributeGroup;
use App\Models\Category;
use Illuminate\Support\Facades\Validator;

class CategoryAttributeController extends BaseController
{
	/**
	 * @OA\Get(
	 *     path="/api/category-attributes",
	 *     summary="Get Categrory List with Atribute & Attribute Group",
	 *     description="Fetches a list of categrory with atribute & attribute group.",
	 *     tags={"Category Attribute Group"},
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="global", in="query", description="Global search for All field", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="id", in="query", description="Search by category id", @OA\Schema(type="integer")),
	 *     @OA\Parameter(name="name", in="query", description="Search by category name", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "name", "attribute_count"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		if (!auth()->user()->can('list product family attribute group')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$searchableColumns = ['id', 'name'];
		$sortableColumns = array_merge($searchableColumns, ['attribute_count']);
		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		/* Base query: categories without children */
		$recordsQuery = Category::whereDoesntHave('children');

		/* Only apply filtering/sorting when pagination is requested */
		if ($request->filled('page') && $request->filled('length')) {

			/* Add attribute_count via subquery */
			$recordsQuery->select('categories.id', 'categories.name')->selectSub(function ($query) {
				$query->from('category_attribute_groups')
				->join('attributes', 'attributes.attribute_group_id', '=', 'category_attribute_groups.attribute_group_id')
				->whereColumn('category_attribute_groups.category_id', 'categories.id')
				->selectRaw('COUNT(DISTINCT attributes.id)');
			}, 'attribute_count');

			/* Apply global or column-specific filters */
			if ($request->filled('global')) {
				$search = $request->input('global');
				$recordsQuery->where(function ($q) use ($searchableColumns, $search) {
					foreach ($searchableColumns as $col) {
						$q->orWhere($col, 'LIKE', '%' . $search . '%');
					}
				});
			} else {
				foreach ($searchableColumns as $col) {
					if ($request->filled($col)) {
						$recordsQuery->where($col, 'LIKE', '%' . $request->input($col) . '%');
					}
				}
			}

			/* Apply sorting */
			$recordsQuery->orderBy($sortBy, $sortDir);

			/* Clone query for counting */
			$totalRecords = (clone $recordsQuery)->count();
			$length = (int) $request->input('length');
			$totalPages = (int) ceil($totalRecords / $length);

			$page = (int) $request->input('page');
			/* If requested page exceeds total pages (after search), fallback to page 1 */
			if ($page > $totalPages && $totalPages > 0) {
				$page = 1;
			}

			$records = $recordsQuery->offset(($page - 1) * $length)
			->limit($length)
			->get();
		} else {
			$records = $recordsQuery->orderBy('name', 'asc')->get(['id', 'name']);
			$totalRecords = $records->count();
			$totalPages = 1;
		}

		return response()->json([
			'success' => true,
			'message' => 'Product family list with attribute count',
			'data' => $records,
			'total_pages' => $totalPages,
			'total_records' => $totalRecords,
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/category-attributes/{category_id}",
	 *     summary="Get Specific Categrory with Atribute & Attribute Group",
	 *     description="Fetches a categrory with atribute attribute group based on the given attribute group ID.",
	 *     tags={"Category Attribute Group"},
	 *     @OA\Parameter(
	 *         name="category_id",
	 *         in="path",
	 *         required=true,
	 *         description="ID of the category",
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($id)
	{
		if (!auth()->user()->can('show product family attribute group')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$record = Category::with([
			'categoryAttributeGroups:id,name',
			'categoryAttributeGroups.groupsAttributes:id,attribute_group_id,code,name'
		])->whereDoesntHave('children')
		->select(['id', 'name', 'parent_id'])
		->where('id', $id)
		->first();

		if (!$record) {
			return response()->json([
				'success' => false,
				'message' => 'Product family not found.'
			]);
		}
		$record->categoryAttributeGroups->each->makeHidden(['pivot']);
		$record->categoryAttributeGroups->each(function ($group) {
			$group->groupsAttributes->each->makeHidden(['attribute_group_id']);
		});

		return response()->json([
			'success' => true,
			'message' => 'Product family with Attribute & Attribute Group',
			'data' => $record
		]);
	}

	/**
	 * @OA\Put(
	 *     path="/api/category-attributes/{category_id}",
	 *     summary="Update a Categrory Atribute & Attribute Group",
	 *     description="Updates Atribute & Attribute Group of a category based on the provided JSON payload.",
	 *     tags={"Category Attribute Group"},
	 *     @OA\Parameter(
	 *         name="category_id",
	 *         in="path",
	 *         required=true,
	 *         description="ID of the category",
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"attribute_group_ids"},
	 *             @OA\Property(
	 *                 property="attribute_group_ids",
	 *                 type="array",
	 *                 description="Array of attribute group IDs to associate with the category",
	 *                 @OA\Items(type="integer", example=3)
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function update(Request $request, $id)
	{
		if (!auth()->user()->can('update product family attribute group')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$record = Category::whereDoesntHave('children')->where('id', $id)->first();

		if (!$record) {
			return response()->json([
				'success' => false,
				'message' => 'Product family not found.'
			]);
		}

		$request->validate([
			'attribute_group_ids' => 'array',
			'attribute_group_ids.*' => 'integer|exists:attribute_groups,id',
		]);

		DB::beginTransaction();

		try {
			// $existingGroupIds = $record->categoryAttributeGroups()->pluck('attribute_group_id')->toArray();
			// $syncData = collect($request->attribute_group_ids)->mapWithKeys(function ($id) use ($existingGroupIds) {
			// 	if (in_array($id, $existingGroupIds)) {
			// 		/* Existing group, do not modify created_by, created_at */
			// 		return [
			// 			$id => []
			// 		];
			// 	} else {
			// 		/* New group, set created_by, created_at */
			// 		return [
			// 			$id => [
			// 				'created_by' => auth()->id() ?? 1,
			// 				'created_at' => now()
			// 			]
			// 		];
			// 	}
			// })->toArray();
			$record->categoryAttributeGroups()->sync($request->attribute_group_ids);

			/* Fetch updated data with only required fields */
			$updatedRecord = Category::whereDoesntHave('children')
			->select(['id', 'name', 'parent_id'])
			->with('categoryAttributeGroups:id,name')
			->where('id', $id)
			->first();

			/* Ensure we don't try to access null values */
			if ($updatedRecord) {
				$updatedRecord->categoryAttributeGroups->each->makeHidden(['pivot']);
			}

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => 'Product family successfully updated with attribute groups.',
				'data' => $updatedRecord,
			], 200);

		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'An error occurred while updating the Product family. Please try again.',
				'error' => $e->getMessage(),
			], 500);
		}
	}

	/**
	 * @OA\Delete(
	 *     path="/api/category-attributes/{id}/remove-attribute-group/{attribute_group_id}",
	 *     summary="Remove an attribute group from a category",
	 *     tags={"Category Attribute Group"},
	 *     @OA\Parameter(name="id", in="path", description="Category ID", required=true, @OA\Schema(type="integer")),
	 *     @OA\Parameter(name="attribute_group_id", in="path", description="Attribute Group ID to remove from the category", required=true, @OA\Schema(type="integer")),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function removeAttributeGroup($id, $attribute_group_id)
	{
		$record = Category::whereDoesntHave('children')->where('id', $id)->first();

		if (!$record) {
			return response()->json(['success' => false, 'message' => 'Product family not found']);
		}

		if (!AttributeGroup::find($attribute_group_id)) {
			return response()->json(['success' => false, 'message' => 'Attribute group not found']);
		}

		DB::beginTransaction();

		try {
			$record->categoryAttributeGroups()->detach($attribute_group_id);

			/* Updated record */
			$record = Category::whereDoesntHave('children')
			->select(['id', 'name', 'parent_id'])
			->with('categoryAttributeGroups:id,name')
			->where('id', $id)
			->first();
			$record->categoryAttributeGroups->each->makeHidden(['pivot']);

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => 'Attributes groups removed successfully.',
				'data' => $record
			], 200);

		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'An error occurred while removing attributes.',
				'error' => $e->getMessage(),
			], 500);
		}
	}

	/**
	 * @OA\Get(
	 *     path="/api/category/getAttributesByCategory/{category_id}",
	 *     summary="Get all attributes assigned to a specific category",
	 *     tags={"Category Attribute Group"},
	 *     @OA\Parameter(name="category_id", in="path", required=true, description="The ID of the category to fetch attributes", @OA\Schema(type="integer")),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function getAttributesByCategory($category_id)
	{
		$record = Category::whereDoesntHave('children')->where('id', $category_id)->first();

		if (!$record) {
			return response()->json(['success' => false, 'message' => 'Product family not found']);
		}

		$attributes = $record->categoryAllAttributes()->map(function ($attribute) {
			return $attribute->only(['id', 'name']);
		});

		return response()->json([
			'success' => true,
			'message' => 'Product family attribute list.',
			'data' => $attributes
		]);
	}
}