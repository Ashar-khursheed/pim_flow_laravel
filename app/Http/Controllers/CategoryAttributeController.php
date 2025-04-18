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
	 * Display a listing of the resource.
	 */
	/**
	 * @OA\Get(
	 *     path="/api/category-attributes",
	 *     summary="Get Categrory List with Atribute & Attribute Group",
	 *     description="Fetches a list of categrory with atribute & attribute group.",
	 *     tags={"Category Attribute Group"},
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
		$records = Category::with([
			'categoryAttributes:id,name',
			'attributeGroups:id,name',
			'attributeGroups.groupAttributes:id,code,name'
		])->whereDoesntHave('children');

		/* Pagination */
		if ($request->filled('page') && $request->filled('length')) {
			$page = (int) $request->input('page');
			$length = (int) $request->input('length');
			$totalRecords = $records->count();
			$totalPages = ceil($totalRecords / $length);

			$records = $records->offset(($page - 1) * $length)->limit($length)->get(['id', 'name', 'parent_id']);
		} else {
			$records = $records->get(['id', 'name', 'parent_id']);
			$totalRecords = $records->count();
		}

		// Hide pivot data manually
		$records->each(function ($category) {
			$category->categoryAttributes->each->makeHidden(['pivot']);
			$category->attributeGroups->each->makeHidden(['pivot']);
			$category->attributeGroups->each(function ($group) {
				$group->groupAttributes->each->makeHidden(['pivot']);
			});
		});

		return response()->json([
			'success' => true,
			'message' => 'Categrory List with Atribute & Attribute Group',
			'data' => $records,
			'total_pages' => $totalPages ?? 1,
			'total_records' => $totalRecords,
		]);
	}

	/**
	 * Display the specified resource.
	 */
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
		if (!auth()->user()->can('view product family attribute group')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$record = Category::with([
			'categoryAttributes:id,name',
			'attributeGroups:id,name',
			'attributeGroups.groupAttributes:id,code,name'
		])->whereDoesntHave('children')
		->select(['id', 'name', 'parent_id'])
		->where('id', $id)
		->first();

		if (!$record) {
			return response()->json([
				'success' => false,
				'message' => 'Record does not exist with given ID.'
			]);
		}
		$record->categoryAttributes->each->makeHidden(['pivot']);
		$record->attributeGroups->each->makeHidden(['pivot']);
		$record->attributeGroups->each(function ($group) {
			$group->groupAttributes->each->makeHidden(['pivot']);
		});

		return response()->json([
			'success' => true,
			'message' => 'Category with Attribute & Attribute Group',
			'data' => $record
		]);
	}

	/**
	 * Update the specified resource in storage.
	 */
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
	 *             required={"attribute_ids", "attribute_group_ids"},
	 *             @OA\Property(
	 *                 property="attribute_ids",
	 *                 type="array",
	 *                 description="Array of attribute IDs to associate with the category",
	 *                 @OA\Items(type="integer", example=5)
	 *             ),
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
		$record = Category::whereDoesntHave('children')
		->select(['id', 'name', 'parent_id'])
		->with(['categoryAttributes:id,name', 'attributeGroups:id,name'])
		->where('id', $id)
		->first();

		if (!$record) {
			return response()->json([
				'success' => false,
				'message' => 'Record does not exist with given ID.'
			]);
		}

		$request->validate([
			'attribute_ids' => 'array',
			'attribute_ids.*' => 'integer|exists:attributes,id',
			'attribute_group_ids' => 'array',
			'attribute_group_ids.*' => 'integer|exists:attribute_groups,id',
		]);

		DB::beginTransaction();

		try {
			$record->categoryAttributes()->sync($request->attribute_ids);
			$record->attributeGroups()->sync($request->attribute_group_ids);

			/* Fetch updated data with only required fields */
			$updatedRecord = Category::whereDoesntHave('children')
			->select(['id', 'name', 'parent_id'])
			->with(['categoryAttributes:id,name', 'attributeGroups:id,name'])
			->where('id', $id)
			->first();

			/* Ensure we don't try to access null values */
			if ($updatedRecord) {
				$updatedRecord->attributeGroups->each->makeHidden(['pivot']);
				$updatedRecord->categoryAttributes->each->makeHidden(['pivot']);
			}

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => 'Category successfully updated with attributes and attribute groups.',
				'data' => $updatedRecord,
			], 200);

		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'An error occurred while updating the category. Please try again.',
				'error' => $e->getMessage(),
			], 500);
		}
	}

	/**
	 * @OA\Post(
	 *     path="/api/category-attributes/{id}/add-attribute",
	 *     summary="Add specific attributes or attribute groups to a category",
	 *     tags={"Category Attribute Group"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Category ID",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             @OA\Property(property="attribute_ids", type="array", @OA\Items(type="integer")),
	 *             @OA\Property(property="attribute_group_ids", type="array", @OA\Items(type="integer"))
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function addAttributes(Request $request, $id)
	{
		$record = Category::whereDoesntHave('children')->find($id);
		if (!$record) {
			return response()->json([
				'success' => false,
				'message' => 'Record does not exist with given ID.'
			]);
		}

		$request->validate([
			'attribute_ids' => 'array',
			'attribute_ids.*' => 'integer|exists:attributes,id',
			'attribute_group_ids' => 'array',
			'attribute_group_ids.*' => 'integer|exists:attribute_groups,id',
		]);

		DB::beginTransaction();

		try {
			$record->categoryAttributes()->syncWithoutDetaching($request->attribute_ids);
			$record->attributeGroups()->syncWithoutDetaching($request->attribute_group_ids);

			/* Updated record */
			$record = Category::whereDoesntHave('children')
			->select(['id', 'name', 'parent_id'])
			->with(['categoryAttributes:id,name', 'attributeGroups:id,name'])
			->where('id', $id)
			->first();
			$record->attributeGroups->each->makeHidden(['pivot']);
			$record->categoryAttributes->each->makeHidden(['pivot']);

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => 'Attributes and attribute groups added successfully.',
				'data' => $record,
			], 200);

		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'An error occurred while adding attributes.',
				'error' => $e->getMessage(),
			], 500);
		}
	}

	/**
	 * @OA\Delete(
	 *     path="/api/category-attributes/{id}/remove-attribute",
	 *     summary="Remove specific attributes or attribute groups from a category",
	 *     tags={"Category Attribute Group"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Category ID",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             @OA\Property(property="attribute_ids", type="array", @OA\Items(type="integer")),
	 *             @OA\Property(property="attribute_group_ids", type="array", @OA\Items(type="integer"))
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function removeAttributes(Request $request, $id)
	{
		$record = Category::whereDoesntHave('children')->find($id);

		if (!$record) {
			return response()->json(['success' => false, 'message' => 'Category not found'], 404);
		}

		$request->validate([
			'attribute_ids' => 'array',
			'attribute_ids.*' => 'integer|exists:attributes,id',
			'attribute_group_ids' => 'array',
			'attribute_group_ids.*' => 'integer|exists:attribute_groups,id',
		]);

		DB::beginTransaction();

		try {
			$record->categoryAttributes()->detach($request->attribute_ids);
			$record->attributeGroups()->detach($request->attribute_group_ids);

			/* Updated record */
			$record = Category::whereDoesntHave('children')
			->select(['id', 'name', 'parent_id'])
			->with(['categoryAttributes:id,name', 'attributeGroups:id,name'])
			->where('id', $id)
			->first();
			$record->attributeGroups->each->makeHidden(['pivot']);
			$record->categoryAttributes->each->makeHidden(['pivot']);

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => 'Attributes and attribute groups removed successfully.',
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
 *     @OA\Parameter(
 *         name="category_id",
 *         in="path",
 *         required=true,
 *         description="The ID of the category to fetch attributes for",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="A list of attributes assigned to the specified category",
 *         @OA\MediaType(mediaType="application/json",
 *             @OA\Schema(
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="id", type="integer"),
 *                     @OA\Property(property="name", type="string"),
 *                     @OA\Property(property="type", type="string"),
 *                     @OA\Property(property="is_required", type="boolean")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Category not found",
 *         @OA\MediaType(mediaType="application/json",
 *             @OA\Schema(
 *                 type="object",
 *                 @OA\Property(property="message", type="string", example="Category not found")
 *             )
 *         )
 *     ),
 *     security={{"bearerAuth":{}}}
 * )
 */

 public function getAttributesByCategory($category_id)
 {
	 // Step 1: Validate that category_id is a valid integer and exists in the database
	 $validated = Validator::make(['category_id' => $category_id], [
		 'category_id' => 'required|integer|exists:attribute_group_categories,category_id',
	 ]);

	 // If validation fails, return error response
	 if ($validated->fails()) {
		 return response()->json([
			 'message' => 'Invalid category ID or category not found'
		 ], 404);
	 }

	 // Step 2: Find the category by ID
	 $category = Category::findOrFail($category_id);

	 // Step 3: Get the attribute groups related to the category
	 $attributeGroups = $category->attributeGroups(); // Access related attribute groups

	 // Step 4: Get all the attributes from those groups, plucking only the 'id' and 'name' fields
	 $attributes = $attributeGroups->with('groupAttributes') // Load groupAttributes for each group
								   ->get() // Get the groups
								   ->pluck('groupAttributes') // Extract the groupAttributes collection
								   ->flatten() // Flatten into a single collection of attributes
								   ->map(function ($attribute) {
									   return [
										   'id' => $attribute->id,
										   'name' => $attribute->name,
									   ];
								   }); // Map to include only id and name

	 // Step 5: Return the attributes in the response
	 return response()->json($attributes);
 }


}