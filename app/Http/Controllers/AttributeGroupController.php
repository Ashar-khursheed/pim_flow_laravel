<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\AttributeGroup;
use App\Models\Category;
use App\Models\Attribute;

class AttributeGroupController extends BaseController
{
	/**
	 * @OA\Get(
	 *     path="/api/attribute-groups",
	 *     summary="Get Attribute Group List",
	 *     description="Fetches a list of attribute groups.",
	 *     tags={"Attribute Group"},
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="global", in="query", description="Global search for All field", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="id", in="query", description="Search by attribute group id", @OA\Schema(type="integer")),
	 *     @OA\Parameter(name="name", in="query", description="Search by attribute group name", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "name", "groups_attributes_count", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *
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
		$searchableColumns = ['id', 'name'];
		$sortableColumns = array_merge($searchableColumns, ['created_at', 'updated_at', 'groups_attributes_count']);
		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
		// $records = AttributeGroup::with(['categories:id,name,parent_id', 'groupsAttributes:id,code,name']);
		$recordsQuery = AttributeGroup::query();

		/* Pagination */
		if ($request->filled('page') && $request->filled('length')) {
			$recordsQuery->withCount('groupsAttributes')->with(['creator:id,first_name,last_name']);
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

			$page = (int) $request->input('page');
			$length = (int) $request->input('length');
			$totalRecords = $recordsQuery->count();
			$totalPages = ceil($totalRecords / $length);

			$records = $recordsQuery->offset(($page - 1) * $length)->limit($length)->get([
				'id', 'name', 'created_by', 'created_at', 'updated_at'
			]);
			$records->transform(function ($record) {
				$record->attribute_count = $record->groups_attributes_count;
				unset($record->groups_attributes_count);

				$record->created_by = $record->creator->name;
				unset($record->creator);

				return $record;
			});
		} else {
			$records = $recordsQuery->orderBy('name', 'asc')->get([
				'id', 'name'
			]);
			$totalRecords = $records->count();
			$totalPages = 1;
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
	 *             @OA\Property(property="category_ids", type="array", @OA\Items(type="integer", example=5)),
	 *             @OA\Property(property="attribute_ids", type="array", @OA\Items(type="integer", example=1))
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
			'category_ids' => 'array',
			'category_ids.*' => 'integer|exists:ec_product_categories,id',
			'attribute_ids' => 'array',
			'attribute_ids.*' => 'integer|exists:attributes,id',
		]);

		/* Ensure only leaf (last-level) categories are used */
		$validLeafCategoryIds = Category::whereDoesntHave('children')->pluck('id')->all();

		if (!empty($request->category_ids)) {
			if (array_diff($request->category_ids, $validLeafCategoryIds)) {
				return response()->json([
					'success' => false,
					'message' => 'Only leaf-level categories (categories without children) can be selected.',
				]);
			}
		}

		/* Check for already associated attributes */
		if (!empty($request->attribute_ids)) {
			$alreadyGroupedAttributes = Attribute::whereIn('id', $request->attribute_ids)
			->whereNotNull('attribute_group_id')
			->pluck('id')
			->all();

			if (!empty($alreadyGroupedAttributes)) {
				return response()->json([
					'success' => false,
					'message' => 'Some attributes are already assigned to another attribute group and cannot be reassigned.',
					'conflicts' => $alreadyGroupedAttributes
				]);
			}
		}
		DB::beginTransaction();

		try {
			$attributeGroup = AttributeGroup::create([
				'name' => $request->name
			]);

			if (!empty($request->category_ids)) {
				$attributeGroup->categories()->sync($request->category_ids);
			}

			if (!empty($request->attribute_ids)) {
				Attribute::whereIn('id', $request->attribute_ids)
				->update(['attribute_group_id' => $attributeGroup->id]);
			}

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => 'Attribute Group created successfully with associated categories and attributes.',
				// 'data' => $attributeGroup->load(['categories:id,name,parent_id', 'groupsAttributes:id,code,name,attribute_group_id'])
				'data' => $attributeGroup
			]);

		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'An error occurred while creating the attribute group.',
				'error' => $e->getMessage()
			], 500);
		}
	}

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
		$record = AttributeGroup::with('categories:id,name', 'groupsAttributes:id,name,code,attribute_group_id')->find($id);

		if (!$record) {
			return response()->json([
				'success' => false,
				'message' => 'Record does not exist with given ID.'
			]);
		}

		$record->categories->each->makeHidden(['pivot']);
		$record->groupsAttributes->each->makeHidden(['attribute_group_id']);

		return response()->json([
			'success' => true,
			'message' => 'Attribute group detail',
			'data' => $record
		]);
	}

	/**
	 * @OA\Put(
	 *     path="/api/attribute-groups/{attribute_group_id}",
	 *     summary="Update a attribute group",
	 *     description="Updates an existing attribute group based on the provided JSON payload.",
	 *     operationId="updateAttributeGroup",
	 *     tags={"Attribute Group"},
	 *     @OA\Parameter(name="attribute_group_id", in="path", required=true, description="ID of the attribute group", @OA\Schema(type="integer", example=1)),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"name"},
	 *             @OA\Property(property="name", type="string", example="Electronics Equipments"),
	 *             @OA\Property(property="category_ids", type="array", @OA\Items(type="integer", example=5)),
	 *             @OA\Property(property="attribute_ids", type="array", @OA\Items(type="integer", example=1))
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

		// dd($request->all());

		$request->validate([
			'name' => 'required|unique:attribute_groups,name,'.$id,
			'category_ids' => 'array',
			'category_ids.*' => 'integer|exists:ec_product_categories,id',
			'attribute_ids' => 'array',
			'attribute_ids.*' => 'integer|exists:attributes,id',
		]);

		/* Ensure only leaf (last-level) categories are used */
		$validLeafCategoryIds = Category::whereDoesntHave('children')->pluck('id')->all();

		if (array_diff($request->category_ids, $validLeafCategoryIds)) {
			return response()->json([
				'success' => false,
				'message' => 'Only leaf-level categories (categories without children) can be selected.',
			]);
		}

		/* Check for already associated attributes */
		if (!empty($request->attribute_ids)) {
			$alreadyGroupedAttributes = Attribute::whereIn('id', $request->attribute_ids)
			->whereNotNull('attribute_group_id')
			->where('attribute_group_id', '!=', $attributeGroup->id)
			->pluck('id')
			->all();

			if (!empty($alreadyGroupedAttributes)) {
				return response()->json([
					'success' => false,
					'message' => 'Some attributes are already assigned to another attribute group and cannot be reassigned.',
					'conflicts' => $alreadyGroupedAttributes
				]);
			}
		}

		DB::beginTransaction();

		try {
			/* Update attribute group name */
			$attributeGroup->name = $request->name;
			$attributeGroup->save();

			if (!empty($request->category_ids)) {
				$attributeGroup->categories()->sync($request->category_ids);
			}

			if (!empty($request->attribute_ids)) {
				/* Detach attributes that were previously in this group but are not in the new list */
				Attribute::where('attribute_group_id', $attributeGroup->id)
				->whereNotIn('id', $request->attribute_ids)
				->update(['attribute_group_id' => null]);

				/* Assign submitted attributes to this group */
				Attribute::whereIn('id', $request->attribute_ids)
				->update(['attribute_group_id' => $attributeGroup->id]);
			}

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => 'Attribute Group updated successfully with associated categories and attributes.',
				// 'data' => $attributeGroup->load(['categories:id,name,parent_id', 'groupsAttributes:id,code,name,attribute_group_id'])
				'data' => $attributeGroup
			]);
		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'Failed to update attribute group.',
				'error' => $e->getMessage()
			]);
		}
	}

	/**
	 * @OA\Delete(
	 *     path="/api/attribute-groups/{id}",
	 *     summary="Delete an attribute group",
	 *     description="Deletes an attribute group along with its associated records in the attribute_group_attributes table.",
	 *     operationId="deleteAttributeGroup",
	 *     tags={"Attribute Group"},
	 *     @OA\Parameter(name="id", in="path", description="ID of the attribute group to delete", required=true, @OA\Schema(type="integer", example=1)),
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
			$attributeGroup->categories()->detach();
			Attribute::where('attribute_group_id', $attributeGroup->id)->update(['attribute_group_id' => null]);

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

	/**
	 * @OA\Delete(
	 *     path="/api/attribute-groups/{id}/remove-attribute/{attribute_id}",
	 *     summary="Remove an attribute from an attribute group",
	 *     tags={"Attribute Group"},
	 *     @OA\Parameter(name="id", in="path", description="Attribute Group ID", required=true, @OA\Schema(type="integer")),
	 *     @OA\Parameter(name="attribute_id", in="path", description="Attribute ID to remove from the attribute group", required=true, @OA\Schema(type="integer")),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function removeAttribute($id, $attribute_id)
	{
		$attributeGroup = AttributeGroup::find($id);

		if (!$attributeGroup) {
			return response()->json([
				'success' => false,
				'message' => 'Record does not exist with given ID.'
			]);
		}

		$attribute = Attribute::find($attribute_id);
		if (!$attribute) {
			return response()->json(['success' => false, 'message' => 'Attribute not found']);
		}

		$attribute->update(['attribute_group_id' => null]);

		return response()->json([
			'success' => true,
			'message' => 'Attributes removed successfully.',
		]);
	}
}