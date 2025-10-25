<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;


class TemporaryCategoryController extends BaseController
{
	/**
	 * @OA\Get(
	 *     path="/api/temporaryCategories",
	 *     summary="Get Temporary Category List",
	 *     description="Fetches a list of categories. If 'type' is set to 'Parent', only parent categories will be returned. If 'parent_id' is provided, it fetches all child categories of the given parent.",
	 *     tags={"Temp Categories"},
	 *     @OA\Parameter(name="type", in="query", description="Filter categories by type.", @OA\Schema(type="string", enum={"All", "Super Parent", "Leaf Child"}, default="All")),
	 *     @OA\Parameter(name="parent_id", in="query", description="Fetch all child categories of a given parent_id", example=1, @OA\Schema(type="integer")),
	 *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"published", "draft", "pending"})),
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *
	 *     @OA\Parameter(name="global", in="query", description="Global search for All field", @OA\Schema(type="string")),
	 *
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "name", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		if (!auth()->user()->can('list category')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}

		$searchableColumns = ['id', 'name'];
		$sortableColumns = array_merge($searchableColumns, ['created_at', 'updated_at']);
		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = Category::query();

		/* Filter by type */
		if ($request->type == 'Super Parent') {
			$recordsQuery = $recordsQuery->where('parent_id', 0);
		} elseif ($request->type == 'Leaf Child') {
			// Categories that are leaf nodes themselves (no children)
			$recordsQuery = $recordsQuery->whereDoesntHave('children');
		} elseif ($request->has('parent_id') && is_numeric($request->parent_id)) {
			$recordsQuery = $recordsQuery->where('parent_id', (int) $request->parent_id);
		}

		/* Filter by status */
		if ($request->has('status') && in_array($request->status, ['published', 'draft', 'pending'])) {
			$recordsQuery = $recordsQuery->where('status', $request->status);
		}

		/* Pagination */
		if ($request->filled('page') && $request->filled('length')) {
			/* Apply global or column-specific filters */
			if ($request->filled('global')) {
				$search = $request->input('global');
				$recordsQuery->where(function ($q) use ($searchableColumns, $search) {
					foreach ($searchableColumns as $col) {
						$q->orWhere($col, 'LIKE', '%' . $search . '%');
					}
				});
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

			$records = $recordsQuery->offset(($page - 1) * $length)->limit($length)->get([
				'id',
				'name',
				'parent_id',
				'description',
				'status',
				'order',
				'image',
				'is_featured',
				'icon',
				'icon_image',
				'slug',
				'last_child' // Include last_child column
			]);

			$records->transform(function ($record) {
				if ($record->image) {
					$record->image = asset('storage/' . $record->image);
				}
				if ($record->icon_image) {
					$record->icon_image = asset('storage/' . $record->icon_image);
				}

				$lastChildIds = !empty($record->last_child)
					? array_map('intval', explode(',', $record->last_child))
					: [];
		 
				if (!empty($lastChildIds)) {
					$record->last_children = Category::whereIn('id', $lastChildIds)
						->get(['id', 'name', 'slug']);
				} else {
					$record->last_children = collect();
				}
				return $record;
			});
		} else {
			$records = $recordsQuery->orderBy('name', 'asc')->get([
				'id',
				'name',
				'parent_id',
				'order',
				'last_child' // Include last_child column
			]);

			// Transform records to include parsed last_child_ids
			$records->transform(function ($record) {


				$lastChildIds = !empty($record->last_child)
					? array_map('intval', explode(',', $record->last_child))
					: [];
		 
				if (!empty($lastChildIds)) {
					$record->last_children = Category::whereIn('id', $lastChildIds)
						->get(['id', 'name', 'slug']);
				} else {
					$record->last_children = collect();
				}
				return $record;
			});

			$totalRecords = $records->count();
			$totalPages = 1;
		}

		return response()->json([
			'success' => true,
			'message' => 'Category List',
			'categories' => $records,
			'total_pages' => $totalPages ?? 1,
			'total_records' => $totalRecords,
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/allTemporaryCategories",
	 *     summary="Get All Temp Categories",
	 *     description="Fetches a hierarchical list of categories. Each category includes its child categories recursively.",
	 *     tags={"Temp Categories"},
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
	 *                         @OA\Property(property="id", type="integer", example=85),
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
	public function allTemporaryCategories(): JsonResponse
	{
		if (!auth()->user()->can('list category')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$categories = Cache::remember('all_categories', 3600, function () {
			return Category::where('parent_id', 0)
				->with(['childrenRecursive'])
				->orderBy('order', 'asc')
				->get(['id', 'name', 'slug', 'order', 'parent_id']);
		});

		return response()->json([
			'success' => true,
			'message' => 'All Categories List',
			'categories' => $categories
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/temporaryCategories",
	 *     summary="Create new category",
	 *     description="Creates a new category with the given details",
	 *     tags={"Temp Categories"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"name"},
	 *                 @OA\Property(property="name", type="string", example="Electronics"),
	 *                 @OA\Property(property="parent_id", type="integer", example=0),                
	 *                 @OA\Property(property="last_child", type="string", example="635,665,686"),
	 *   @OA\Property(property="slug", type="string", example="electronics")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=201,
	 *         description="Category created successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Category created successfully"),
	 *             @OA\Property(property="category", type="object")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=422,
	 *         description="Validation error",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="Validation error"),
	 *             @OA\Property(property="errors", type="object")
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request): JsonResponse
	{
		if (!auth()->user()->can('add category')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$validator = Validator::make($request->all(), [
			'name' => 'required|string|max:191',
			'parent_id' => [
				'nullable',
				'integer',
				function ($attribute, $value, $fail) {
					if ($value != 0 && !Category::where('id', $value)->exists()) {
						$fail('The selected parent category is invalid.');
					}
				}
			],
			'slug' => 'nullable|string|max:191|unique:categories,slug',
			'last_child' => 'nullable|string|regex:/^(\d+)(,\d+)*$/',
		]);

		$disk = 's3'; // or use config

		if ($validator->fails()) {
			return response()->json([
				'success' => false,
				'message' => 'Validation error',
				'errors' => $validator->errors()
			], 422);
		}

		try {
			$data = $validator->validated();

			// Generate a slug if one isn't provided
			if (empty($data['slug'])) {
				$data['slug'] = Str::slug($data['name']);
			}



			// Create the category
			$category = Category::create($data);

			// Clear cache
			Cache::forget('all_categories');


			return response()->json([
				'success' => true,
				'message' => 'Category created successfully',
				'category' => $category
			], 201);

		} catch (\Exception $e) {

			return response()->json([
				'success' => false,
				'message' => 'Failed to create category',
				'error' => $e->getMessage()
			], 500);
		}
	}

	/**
	 * @OA\Get(
	 *     path="/api/temporaryCategories/{id}",
	 *     summary="Get Temporary category details",
	 *     description="Returns details of a specific category",
	 *     tags={"Temp Categories"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="Category ID",
	 *         @OA\Schema(
	 *             type="integer"
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Success",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="category", type="object")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Category not found",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="Category not found")
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($id): JsonResponse
	{
		if (!auth()->user()->can('show category')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		try {
			$category = Category::findOrFail($id);

			// Load children if any
			$category->load('children');

			$lastChildIds = !empty($category->last_child)
					? array_map('intval', explode(',', $category->last_child))
					: [];
		 
				if (!empty($lastChildIds)) {
					$category->last_children = Category::whereIn('id', $lastChildIds)
						->get(['id', 'name', 'slug']);
				} else {
					$category->last_children = collect();
				}

			return response()->json([
				'success' => true,
				'category' => $category
			]);

		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Category not found'
			], 404);
		}
	}

	/**
	 * Update the specified category in storage. 
	 * @OA\Post(
	 *     path="/api/temporaryCategories/{id}",
	 *     summary="Update existing category (uses POST due to image upload)",
	 *     description="Updates an existing category with the given details. Uses POST instead of PUT because of file uploads.",
	 *     tags={"Temp Categories"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         @OA\Schema(type="integer"),
	 *         description="Category ID"
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"name"},
	 *                 @OA\Property(property="name", type="string", example="Electronics"),
	 *                 @OA\Property(property="parent_id", type="integer", example=0),                   
	 *                 @OA\Property(property="slug", type="string", example="electronics"),
	 * 				   @OA\Property(property="last_child", type="string", example="635,665,686")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Category updated successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Category updated successfully"),
	 *             @OA\Property(property="category", type="object")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=422,
	 *         description="Validation error",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="Validation error"),
	 *             @OA\Property(property="errors", type="object")
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function update(Request $request, $id)
	{  
		if (!auth()->user()->can('update category')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$category = Category::findOrFail($id);

		$validator = Validator::make($request->all(), [
			'name' => 'required|string|max:191',
			'parent_id' => [
				'nullable',
				'integer',
				function ($attribute, $value, $fail) use ($id) {
					if ($value != 0 && !Category::where('id', $value)->where('id', '!=', $id)->exists()) {
						$fail('The selected parent category is invalid.');
					}
				}
			],
			'slug' => 'nullable|string|max:191|unique:categories,slug,' . $category->id,
			'last_child' => 'nullable|string|regex:/^(\d+)(,\d+)*$/',
		]);

		$disk = 's3';

		if ($validator->fails()) {
			return response()->json([
				'success' => false,
				'message' => 'Validation error',
				'errors' => $validator->errors()
			], 422);
		}

		try {
			$data = $validator->validated();

			if (empty($data['slug'])) {
				$data['slug'] = Str::slug($data['name']);
			}


			$category->update($data);

			Cache::forget('all_categories');

			return response()->json([
				'success' => true,
				'message' => 'Category updated successfully',
				'category' => $category
			], 200);

		} catch (\Exception $e) {

			return response()->json([
				'success' => false,
				'message' => 'Failed to update category',
				'error' => $e->getMessage()
			], 500);
		}
	}

	/**
	 * @OA\Delete(
	 *     path="/api/temporaryCategories/{id}",
	 *     summary="Temp Delete category",
	 *     description="Deletes a category and optionally moves its children to its parent or deletes them",
	 *     tags={"Temp Categories"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="Category ID",
	 *         @OA\Schema(
	 *             type="integer"
	 *         )
	 *     ),
	 *     @OA\Parameter(
	 *         name="delete_children",
	 *         in="query",
	 *         required=false,
	 *         description="Whether to delete all children (true) or move them to parent (false)",
	 *         @OA\Schema(
	 *             type="boolean",
	 *             default=false
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Category deleted successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Category deleted successfully")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Category not found",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="Category not found")
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function destroy(Request $request, $id)
	{  
		if (!auth()->user()->can('delete category')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		try {
			$category = Category::findOrFail($id);
			$deleteChildren = $request->boolean('delete_children', false);

			// Begin transaction
			\DB::beginTransaction();

			// Handle child categories
			if ($deleteChildren) {
				// Delete all children recursively
				$this->deleteChildrenRecursively($category);
			} else {
				// Move children to parent
				$newParentId = $category->parent_id;
				Category::where('parent_id', $id)->update(['parent_id' => $newParentId]);
			}

			// Delete images
			if ($category->image) {
				Storage::disk('public')->delete($category->image);
			}

			if ($category->icon_image) {
				Storage::disk('public')->delete($category->icon_image);
			}

			// Delete the category
			$category->delete();

			// Commit transaction
			\DB::commit();

			// Clear cache
			Cache::forget('all_categories');

			return response()->json([
				'success' => true,
				'message' => 'Category deleted successfully'
			]);

		} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
			return response()->json([
				'success' => false,
				'message' => 'Category not found'
			], 404);
		} catch (\Exception $e) {
			// Rollback transaction on error
			\DB::rollBack();


			return response()->json([
				'success' => false,
				'message' => 'Failed to delete category',
				'error' => $e->getMessage()
			], 500);
		}
	}

	/**
	 * Helper method to recursively delete children categories
	 */
	private function deleteChildrenRecursively(Category $category)
	{
		foreach ($category->children as $child) {
			$this->deleteChildrenRecursively($child);

			// Delete images
			if ($child->image) {
				Storage::disk('public')->delete($child->image);
			}

			if ($child->icon_image) {
				Storage::disk('public')->delete($child->icon_image);
			}

			$child->delete();
		}
	}



	/**
	 * @OA\Get(
	 *     path="/api/allLastChild",
	 *     summary="Get All Last Child Categories",
	 *     description="Fetches a hierarchical list of categories. Each category includes its child categories recursively.",
	 *     tags={"Temp Categories"},
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
	public function allLastChildCategories()
	{
		if (!auth()->user()->can('list category')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$categories = Cache::remember('all_last_child_categories', 3600, function () {
			return Category::whereDoesntHave('children')
				->with(['parent.parent.parent']) // Load parent hierarchy
				->orderBy('order', 'asc')
				->get(['id', 'name', 'slug', 'order', 'parent_id'])
				->map(function ($category) {
					// Build full category path
					$path = $this->getCategoryPath($category);
					return [
						'id' => $category->id,
						'name' => $category->name,
						'slug' => $category->slug,
						'order' => $category->order,
						'parent_id' => $category->parent_id,
						'full_path' => $path,
					];
				});
		});

		return response()->json([
			'success' => true,
			'message' => 'All Last Child Categories List',
			'categories' => $categories
		]);
	}



	// Helper method to build category path
	private function getCategoryPath($category)
	{
		$path = [$category->name];
		$parent = $category->parent;

		while ($parent) {
			array_unshift($path, $parent->name);
			$parent = $parent->parent;
		}

		return implode(' > ', $path);
	}

}