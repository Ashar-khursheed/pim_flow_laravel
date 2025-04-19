<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\AttributeGroup;
use App\Models\Category;

class AttributeGroupController extends BaseController
{
	/**
	 * Display a listing of the resource.
	 */
	/**
	 * @OA\Get(
	 *     path="/api/attribute-groups",
	 *     summary="Get Attribute Group List",
	 *     description="Fetches a list of attribute groups.",
	 *     tags={"Attribute Group"},
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
		if (!auth()->user()->can('list attribute group')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$records = AttributeGroup::with(['categories:id,name,parent_id', 'groupAttributes:id,code,name']);

		/* Pagination */
		if ($request->filled('page') && $request->filled('length')) {
			$page = (int) $request->input('page');
			$length = (int) $request->input('length');
			$totalRecords = $records->count();
			$totalPages = ceil($totalRecords / $length);

			$records = $records->offset(($page - 1) * $length)->limit($length)->get();
		} else {
			$records = $records->get();
			$totalRecords = $records->count();
		}

		return response()->json([
			'success' => true,
			'message' => 'Attribute Group List',
			'data' => $records,
			'total_pages' => $totalPages ?? 1,
			'total_records' => $totalRecords,
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/attribute-groups",
	 *     summary="Create an attribute group",
	 *     tags={"Attribute Group"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"name", "category_ids"},
	 *             @OA\Property(property="name", type="string", example="Electronics Equipments"),
	 *             @OA\Property(
	 *                 property="category_ids",
	 *                 type="array",
	 *                 @OA\Items(type="integer", example=5)
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request)
	{
		if (!auth()->user()->can('add attribute group')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$request->validate([
			'name' => 'required|unique:attribute_groups,name',
			'category_ids' => 'required|array|min:1',
			'category_ids.*' => 'integer|exists:ec_product_categories,id'
		]);

		$leafCategoryIds = Category::whereDoesntHave('children')->pluck('id')->all();

		if (array_diff($request->category_ids, $leafCategoryIds)) {
			return response()->json([
				'success' => false,
				'message' => 'Invalid category. Only last-child categories are allowed.'
			], 400);
		}

		DB::beginTransaction();
		try {
			$attributeGroup = AttributeGroup::create([
				'name' => $request->name
			]);
			if (!empty($request->category_ids)) {
				$attributeGroup->categories()->sync($request->category_ids);
			}

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => 'Attribute group created successfully',
				'data' => $attributeGroup->load(['categories:id,name,parent_id', 'groupAttributes:id,code,name'])
			], 201);

		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'Failed to create attribute group.',
				'error' => $e->getMessage()
			], 500);
		}

	}

	/**
	 * Display the specified resource.
	 */
	/**
	 * @OA\Get(
	 *     path="/api/attribute-groups/{attribute_group_id}",
	 *     summary="Get attribute group details",
	 *     description="Fetches attribute group details based on the given attribute group ID.",
	 *     tags={"Attribute Group"},
	 *     @OA\Parameter(
	 *         name="attribute_group_id",
	 *         in="path",
	 *         required=true,
	 *         description="ID of the attribute group",
	 *         @OA\Schema(type="integer", example=1)
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
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($id, Request $request)
	{
		if (!auth()->user()->can('show attribute group')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$record = AttributeGroup::with('categories:id,name,parent_id')->find($id);

		if (!$record) {
			return response()->json([
				'success' => false,
				'message' => 'Record does not exist with given ID.'
			]);
		}

		if ($request->filled('page') && $request->filled('length')) {
			$page = (int) $request->input('page');
			$length = (int) $request->input('length');

			$groupAttributesQuery = $record->groupAttributes();

			$totalRecords = $groupAttributesQuery->count();
			$totalPages = ceil($totalRecords / $length);

			$groupAttributes = $groupAttributesQuery
			->select(
				'attributes.id',
				'attributes.code',
				'attributes.name',
				'attribute_group_attributes.attribute_group_id',
				'attribute_group_attributes.attribute_id'
			)
			->offset(($page - 1) * $length)
			->limit($length)
			->get();
		} else {
			$groupAttributes = $record->groupAttributes()
			->select(
				'attributes.id',
				'attributes.code',
				'attributes.name',
				'attribute_group_attributes.attribute_group_id',
				'attribute_group_attributes.attribute_id'
			)
			->get();
			$totalRecords = $groupAttributes->count();
			$totalPages = 1;
		}

		return response()->json([
			'success' => true,
			'message' => 'Attribute group detail',
			'data' => [
				[
					'id' => $record->id,
					'name' => $record->name,
					'created_at' => $record->created_at,
					'updated_at' => $record->updated_at,
					'categories' => $record->categories,
					'groupAttributes' => $groupAttributes,
					'total_pages' => $totalPages,
					'total_records' => $totalRecords
				]
			]
		]);
	}

	/**
	 * Update the specified resource in storage.
	 */
	/**
	 * @OA\Put(
	 *     path="/api/attribute-groups/{attribute_group_id}",
	 *     summary="Update a attribute group",
	 *     description="Updates an existing attribute group based on the provided JSON payload.",
	 *     operationId="updateAttributeGroup",
	 *     tags={"Attribute Group"},
	 *     @OA\Parameter(
	 *         name="attribute_group_id",
	 *         in="path",
	 *         required=true,
	 *         description="ID of the attribute group",
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"name", "attribute_ids"},
	 *             @OA\Property(property="name", type="string", example="General Attribute"),
	 *             @OA\Property(
	 *                 property="attribute_ids",
	 *                 type="array",
	 *                 @OA\Items(type="integer", example=5)
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function update(Request $request, $id)
	{
		if (!auth()->user()->can('update attribute group')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$attributeGroup = AttributeGroup::find($id);
		if (!$attributeGroup) {
			return response()->json([
				'success' => false,
				'message' => 'Record does not exist with given ID.'
			]);
		}

		$request->validate([
			'name' => 'required|unique:attribute_groups,name,'.$id,
			'attribute_ids' => 'array',
			// 'attribute_ids' => 'required|array|min:1',
			// 'attribute_ids.*' => 'integer|exists:attributes,id'
		]);

		DB::beginTransaction();

		try {
			// Update attribute group name
			$attributeGroup->name = $request->name;
			$attributeGroup->save();

			// Sync attributes in pivot table
			if ($request->attribute_ids) {
				// code...
				$attributeGroup->groupAttributes()->sync($request->attribute_ids);
			}

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => 'Attribute group updated successfully',
				'data' => $attributeGroup->load(['categories:id,name,parent_id', 'groupAttributes:id,code,name'])
			], 200);

		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'Failed to update attribute group.',
				'error' => $e->getMessage()
			], 500);
		}
	}

	/**
	 * @OA\Delete(
	 *     path="/api/attribute-groups/{id}",
	 *     summary="Delete an attribute group",
	 *     description="Deletes an attribute group along with its associated records in the attribute_group_attributes table.",
	 *     operationId="deleteAttributeGroup",
	 *     tags={"Attribute Group"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="ID of the attribute group to delete",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function destroy($id)
	{
		if (!auth()->user()->can('delete attribute group')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$attributeGroup = AttributeGroup::find($id);

		if (!$attributeGroup) {
			return response()->json([
				'success' => false,
				'message' => 'Record does not exist with given ID.'
			]);
		}

		DB::beginTransaction();

		try {
			/* Delete related records in related tables */
			$attributeGroup->groupAttributes()->detach();
			$attributeGroup->categories()->detach();

			/* Delete the attribute group */
			$attributeGroup->delete();

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => 'Attribute group deleted successfully'
			], 200);

		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'Failed to delete attribute group.',
				'error' => $e->getMessage()
			], 500);
		}
	}
}