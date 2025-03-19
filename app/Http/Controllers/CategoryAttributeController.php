<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\AttributeGroup;
use App\Models\Category;

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
		$records = Category::with(['categoryAttributes:id,name', 'attributeGroups:id,name'])->whereDoesntHave('children');

		if($request->filled('page') && $request->filled('length')){
			$page = $request->input('page');
			$length = $request->input('length');
			$records = $records->offset(($page - 1)*$length)->limit($length);
		}

		$records = $records->get(['id', 'name', 'parent_id']);

		// Hide pivot data manually
		$records->each(function ($category) {
			$category->attributeGroups->each->makeHidden(['pivot']);
			$category->categoryAttributes->each->makeHidden(['pivot']);
		});

		return response()->json([
			'success' => true,
			'message' => 'Categrory List with Atribute & Attribute Group',
			'data' => $records
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
		$record = Category::with([
			'categoryAttributes:id,name',
			'attributeGroups:id,name'
		])->whereDoesntHave('children')
		->select(['id', 'name', 'parent_id'])
		->where('id', $id)
		->first();

		if (!$record) {
			return response()->json([
				'success' => false,
				'message' => 'Record does not exist with given ID.'
			]);
		} else {
			$record->attributeGroups->each->makeHidden(['pivot']);
			$record->categoryAttributes->each->makeHidden(['pivot']);

		}

		return response()->json([
			'success' => true,
			'message' => 'Categrory with Atribute & Attribute Group',
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

}